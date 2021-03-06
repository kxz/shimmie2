<?php

class CustomIndexTheme extends IndexTheme {
	public function display_page($page, $images) {
		global $config, $database;

		if(count($this->search_terms) == 0) {
			$query = null;
			$page_title = $config->get_string('title');
		}
		else {
			$search_string = implode(' ', $this->search_terms);
			$query = url_escape($search_string);
			$page_title = html_escape($search_string);
			if(count($this->search_terms) == 1) {
				$tag_id = $database->db->GetCol("SELECT id FROM tags WHERE tag = ?", $this->search_terms);
				if(count($tag_id) == 1) {
					$tag_id = $tag_id[0];
					resolve_pageid("shimmie", $tag_id, $exists);
					// wl() already does HTML escaping, so we don't need it here
					$edit_link = wl($tag_id, 'do=edit');
					
					if($exists) {
						$content = p_wiki_xhtml($tag_id) . "<p><a href=\"$edit_link\">Edit this description</a></p>";
					} else {
						$content = "<p>No description exists for the tag \"$page_title\". " .
								   "<a href=\"$edit_link\">Create one</a></p>";
					}
					
					$page->add_block(new Block("Wiki", $content, "main", 0));
				}
			}
		}

		$nav = $this->build_navigation($this->page_number, $this->total_pages, $this->search_terms);
		$page->set_title($page_title);
		$page->set_heading($page_title);
		$page->add_block(new Block("Search", $nav, "left", 0));
		if(count($images) > 0) {
			if($query) {
				$page->add_block(new Block("Images", $this->build_table($images, "search=$query"), "main", 10));
				$this->display_paginator($page, "post/list/$query", null, $this->page_number, $this->total_pages);
			}
			else {
				$page->add_block(new Block("Images", $this->build_table($images, null), "main", 10));
				$this->display_paginator($page, "post/list", null, $this->page_number, $this->total_pages);
			}
		}
		else {
			$page->add_block(new Block("No Images Found", "No images were found to match the search criteria"));
		}
	}


	protected function build_navigation($page_number, $total_pages, $search_terms) {
		$h_search_string = count($search_terms) == 0 ? "" : html_escape(implode(" ", $search_terms));
		$h_search_link = make_link();
		$h_search = "
			<p><form action='$h_search_link' method='GET'>
				<input name='search' type='text'
						value='$h_search_string' autocomplete='off' />
				<input type='hidden' name='q' value='/post/list'>
				<input type='submit' value='Find' style='display: none;' />
			</form>
			<div id='search_completions'></div>";

		return $h_search;
	}

	protected function build_table($images, $query) {
		$table = "";
		foreach($images as $image) {
			$table .= "\t<span class=\"thumb\">" . $this->build_thumb_html($image, $query) . "</span>\n";
		}
		return $table;
	}
}
?>
