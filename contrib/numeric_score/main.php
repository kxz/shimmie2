<?php
/*
 * Name: Image Scores (Numeric)
 * Author: Shish <webmaster@shishnet.org>
 * License: GPLv2
 * Description: Allow users to score images
 * Documentation:
 *  Each registered user may vote an image +1 or -1, the
 *  image's score is the sum of all votes.
 */

class NumericScoreSetEvent extends Event {
	var $image_id, $user, $score;

	public function NumericScoreSetEvent($image_id, $user, $score) {
		$this->image_id = $image_id;
		$this->user = $user;
		$this->score = $score;
	}
}

class NumericScore implements Extension {
	var $theme;

	public function receive_event(Event $event) {
		global $config, $database, $page, $user;
		if(is_null($this->theme)) $this->theme = get_theme_object($this);

		if($event instanceof InitExtEvent) {
			if($config->get_int("ext_numeric_score_version", 0) < 1) {
				$this->install();
			}
		}

		if($event instanceof DisplayingImageEvent) {
			if(!$user->is_anonymous()) {
				$votes = $database->get_all(
					"SELECT users.name as username, user_id, score 
					FROM numeric_score_votes 
					JOIN users ON numeric_score_votes.user_id=users.id
					WHERE image_id=? ORDER BY score DESC, username ASC",
					array($event->image->id));
				$upvotes = array();
				$downvotes = array();
				
				foreach($votes as $vote) {
					if($vote['score'] < 0) {
						$downvotes[] = $vote['username'];
					} elseif ($vote['score'] > 0) {
						$upvotes[] = $vote['username'];
					}
				}
				
				$html = $this->theme->get_voter_html($event->image, $upvotes, $downvotes);
				$page->add_block(new Block("Image Score", $html, "left", 20));
			}
		}

		if($event instanceof PageRequestEvent) {
			if($event->page_matches("numeric_score_vote") && $user->check_auth_token()) {
				if(!$user->is_anonymous()) {
					$image_id = int_escape($_POST['image_id']);
					$char = $_POST['vote'];
					$score = null;
					if($char == "up") $score = 1;
					else if($char == "null") $score = 0;
					else if($char == "down") $score = -1;
					if(!is_null($score) && $image_id>0) send_event(new NumericScoreSetEvent($image_id, $user, $score));
					$page->set_mode("redirect");
					$page->set_redirect(make_link("post/view/$image_id"));
				}
			}
			if($event->page_matches("numeric_score/remove_votes_on") && $user->check_auth_token()) {
				if($user->is_admin()) {
					$image_id = int_escape($_POST['image_id']);
					$database->execute(
							"DELETE FROM numeric_score_votes WHERE image_id=?",
							array($image_id));
					$database->execute(
							"UPDATE images SET numeric_score=0 WHERE id=?",
							array($image_id));
					$page->set_mode("redirect");
					$page->set_redirect(make_link("post/view/$image_id"));
				}
			}
			if($event->page_matches("numeric_score/remove_votes_by") && $user->check_auth_token()) {
				if($user->is_admin()) {
					$user_id = int_escape($_POST['user_id']);
					$image_ids = $database->get_col("SELECT image_id FROM numeric_score_votes WHERE user_id=?", array($user_id));

					$database->execute(
							"DELETE FROM numeric_score_votes WHERE user_id=? AND image_id IN ?",
							array($user_id, $image_ids));
					$database->execute(
							"UPDATE images SET numeric_score=(SELECT SUM(score) FROM numeric_score_votes WHERE image_id=images.id) WHERE images.id IN ?",
							array($image_ids));
					$page->set_mode("redirect");
					$page->set_redirect(make_link("post/view/$image_id"));
				}
			}
		}

		if($event instanceof NumericScoreSetEvent) {
			log_info("numeric_score", "Rated Image #{$event->image_id} as {$event->score}");
			$this->add_vote($event->image_id, $user->id, $event->score);
		}

		if($event instanceof ImageDeletionEvent) {
			$database->execute("DELETE FROM numeric_score_votes WHERE image_id=?", array($event->image->id));
		}

		if($event instanceof ParseLinkTemplateEvent) {
			$event->replace('$score', $event->image->numeric_score);
		}

		if($event instanceof SearchTermParseEvent) {
			$matches = array();
			if(preg_match("/^score(<|<=|=|>=|>)(\d+)$/", $event->term, $matches)) {
				$cmp = $matches[1];
				$score = $matches[2];
				$event->add_querylet(new Querylet("numeric_score $cmp $score"));
			}
			if(preg_match("/^upvoted_by=(.*)$/", $event->term, $matches)) {
				$duser = User::by_name($matches[1]);
				if(is_null($duser)) {
					throw new SearchTermParseException(
							"Can't find the user named ".html_escape($matches[1]));
				}
				$event->add_querylet(new Querylet(
					"images.id in (SELECT image_id FROM numeric_score_votes WHERE user_id=? AND score=1)",
					array($duser->id)));
			}
			if(preg_match("/^downvoted_by=(.*)$/", $event->term, $matches)) {
				$duser = User::by_name($matches[1]);
				if(is_null($duser)) {
					throw new SearchTermParseException(
							"Can't find the user named ".html_escape($matches[1]));
				}
				$event->add_querylet(new Querylet(
					"images.id in (SELECT image_id FROM numeric_score_votes WHERE user_id=? AND score=-1)",
					array($duser->id)));
			}
			if(preg_match("/^upvoted_by_id=(\d+)$/", $event->term, $matches)) {
				$iid = int_escape($matches[1]);
				$event->add_querylet(new Querylet(
					"images.id in (SELECT image_id FROM numeric_score_votes WHERE user_id=? AND score=1)",
					array($iid)));
			}
			if(preg_match("/^downvoted_by_id=(\d+)$/", $event->term, $matches)) {
				$iid = int_escape($matches[1]);
				$event->add_querylet(new Querylet(
					"images.id in (SELECT image_id FROM numeric_score_votes WHERE user_id=? AND score=-1)",
					array($iid)));
			}
			if(preg_match("/order=(-?)score/", $event->term, $matches)) {
				$ql = new Querylet('1 = 1');
				$ql->order = "{$matches[1]}images.numeric_score";
				$event->add_querylet($ql);
			}
		}

		if($event instanceof UserPageBuildingEvent) {
			$i_upvote_count = Image::count_images(array("upvoted_by={$event->display_user->name}"));
			$i_downvote_count = Image::count_images(array("downvoted_by={$event->display_user->name}"));
			$i_days_old = ((time() - strtotime($event->display_user->join_date)) / 86400) + 1;
			$h_upvote_rate = sprintf("%.1f", ($i_upvote_count / $i_days_old));
			$h_downvote_rate = sprintf("%.1f", ($i_downvote_count / $i_days_old));
			$upvote_link = make_link("post/list/upvoted_by={$event->display_user->name}/1");
			$downvote_link = make_link("post/list/downvoted_by={$event->display_user->name}/1");
			$event->add_stats("<a href='$upvote_link'>Images upvoted</a>: $i_upvote_count, $h_upvote_rate per day");
			$event->add_stats("<a href='$downvote_link'>Images downvoted</a>: $i_downvote_count, $h_downvote_rate per day");
		}
	}

	private function install() {
		global $database;
		global $config;

		if($config->get_int("ext_numeric_score_version") < 1) {
			$database->Execute("ALTER TABLE images ADD COLUMN numeric_score INTEGER NOT NULL DEFAULT 0");
			$database->Execute("CREATE INDEX images__numeric_score ON images(numeric_score)");
			$database->create_table("numeric_score_votes", "
				image_id INTEGER NOT NULL,
				user_id INTEGER NOT NULL,
				score INTEGER NOT NULL,
				UNIQUE(image_id, user_id),
				INDEX(image_id),
				FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE,
				FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
			");
			$config->set_int("ext_numeric_score_version", 1);
		}
		if($config->get_int("ext_numeric_score_version") < 2) {
			$database->Execute("CREATE INDEX numeric_score_votes__user_votes ON numeric_score_votes(user_id, score)");
			$config->set_int("ext_numeric_score_version", 2);
		}
	}

	private function add_vote($image_id, $user_id, $score) {
		global $database;
		$database->Execute(
			"DELETE FROM numeric_score_votes WHERE image_id=? AND user_id=?",
			array($image_id, $user_id));
		if($score != 0) {
			$database->Execute(
				"INSERT INTO numeric_score_votes(image_id, user_id, score) VALUES(?, ?, ?)",
				array($image_id, $user_id, $score));
		}
		$database->Execute(
			"UPDATE images SET numeric_score=(SELECT SUM(score) FROM numeric_score_votes WHERE image_id=?) WHERE id=?",
			array($image_id, $image_id));
	}
}
add_event_listener(new NumericScore());
?>
