<?php

class NumericScoreTheme extends Themelet {
	public function make_user_list($users) {
		$list = "";
		foreach($users as $user) {
			$list .= "<br><a href='" . make_link("user/$user") . "'>$user</a>";
		}
		return $list;
	}
	
	public function get_voter_html(Image $image, $upvotes, $downvotes) {
		global $user;
		$i_image_id = int_escape($image->id);
		$i_score = int_escape($image->numeric_score);

		$html = "Current Score: $i_score";
		
		if(count($upvotes) > 0) {
			$html .= "<p>Upvoted by:" . $this->make_user_list($upvotes);
		}
		
		if(count($downvotes) > 0) {
			$html .= "<p>Downvoted by:" . $this->make_user_list($downvotes);
		}

		$html .= "
			<p><form action='".make_link("numeric_score_vote")."' method='POST'>
			".$user->get_auth_html()."
			<input type='hidden' name='image_id' value='$i_image_id'>
			<input type='hidden' name='vote' value='up'>
			<input type='submit' value='Vote Up'>
			</form>

			<form action='".make_link("numeric_score_vote")."' method='POST'>
			".$user->get_auth_html()."
			<input type='hidden' name='image_id' value='$i_image_id'>
			<input type='hidden' name='vote' value='null'>
			<input type='submit' value='Remove Vote'>
			</form>

			<form action='".make_link("numeric_score_vote")."' method='POST'>
			".$user->get_auth_html()."
			<input type='hidden' name='image_id' value='$i_image_id'>
			<input type='hidden' name='vote' value='down'>
			<input type='submit' value='Vote Down'>
			</form>
		";
		if($user->is_admin()) {
			$html .= "
			<form action='".make_link("numeric_score/remove_votes_on")."' method='POST'>
			".$user->get_auth_html()."
			<input type='hidden' name='image_id' value='$i_image_id'>
			<input type='submit' value='Remove All Votes'>
			</form>
			";
		}
		return $html;
	}
}

?>
