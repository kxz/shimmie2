<?php

class SVGFileHandlerTheme extends Themelet {
	public function display_image(Page $page, Image $image) {
		$ilink = make_link("get_svg/{$image->id}/{$image->id}.svg");
//		$ilink = $image->get_image_link();
		$html = "<img src='$ilink' alt=''>";
		$page->add_block(new Block("Image", $html, "main", 0));
	}
}
?>
