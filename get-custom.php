<?php

require_once 'RCCWP_Constant.php';
require_once 'tools/debug.php';

/**
 * Get number of group duplicates given field name. The function returns 1
 * if there are no duplicates (just the original group), 2 if there is one
 * duplicate and so on.
 *
 * @param string $fieldName the name of any field in the group
 * @return number of group duplicates 
 */
function getGroupDuplicates ($fieldName) {
	require_once("RCCWP_CustomField.php");
	global $post;
	return RCCWP_CustomField::GetFieldGroupDuplicates($post->ID, $fieldName);
}

/**
 * Get number of field duplicates given field name and group duplicate index.
 * The function returns 1 if there are no duplicates (just the original field), 
 * 2 if there is one duplicate and so on.
 *
 * @param string $fieldName
 * @param integer $groupIndex
 * @return number of field duplicates
 */
function getFieldDuplicates ($fieldName, $groupIndex) {
	require_once("RCCWP_CustomField.php");
	global $post;
	return RCCWP_CustomField::GetFieldDuplicates($post->ID, $fieldName, $groupIndex);
}

/**
 * Get the value of an input field.
 *
 * @param string $fieldName
 * @param integer $groupIndex
 * @param integer $fieldIndex
 * @param boolean $readyForEIP if true and the field type is textbox or
 * 				multiline textbox, the resulting value will be wrapped
 * 				in a div that is ready for EIP. The default value is true
 * @return a string or array based on field type
 */
function get ($fieldName, $groupIndex=1, $fieldIndex=1, $readyForEIP=true,$post_id=NULL) {
	require_once("RCCWP_CustomField.php");
	global $wpdb, $post, $FIELD_TYPES;
	
	if(!$post_id){ $post_id = $post->ID; }
	
	$field = RCCWP_CustomField::GetInfoByName($fieldName,$post_id);
	if(!$field) return FALSE;
	
	$fieldType = $field['type'];
	$fieldID = $field['id'];
	$fieldObject = $field['properties'];
	
	$single = true;
	switch($fieldType){
		case $FIELD_TYPES["checkbox_list"]:
		case $FIELD_TYPES["listbox"]:
			$single = false;
			break;
	} 
	
    // make sure we're fetching the order correctly
    $order = RCCWP_CustomField::GetOrderDuplicates($post_id, $fieldName);
    $groupIndex = $order[$groupIndex];
    
	$fieldValues = (array) RCCWP_CustomField::GetValues($single, $post_id, $fieldName, $groupIndex, $fieldIndex);
    if(empty($fieldValues)) return FALSE;

	$fieldMetaID = RCCWP_CustomField::GetMetaID($post->ID, $fieldName, $groupIndex, $fieldIndex);
	
	$results = GetProcessedFieldValue($fieldValues, $fieldType, $fieldObject);
	
	//filter for multine line
	if($fieldType == $FIELD_TYPES['multiline_textbox']){
		$results = apply_filters('the_content', $results);
	}
	if($fieldType == $FIELD_TYPES['image']){
		$results = split('&',$results);
		$results = $results[0];
	}
	
	// Prepare fields for EIP 
	include_once('RCCWP_Options.php');
	$enableEditnplace = RCCWP_Options::Get('enable-editnplace');
	if ($readyForEIP && $enableEditnplace == 1 && current_user_can('edit_posts', $post->ID)){
	
	    switch($fieldType){
	        case $FIELD_TYPES["textbox"]:
			if(!$results) $results="&nbsp";
			$results = "<div class='".EIP_textbox($fieldMetaID)."' >".$results."</div>";
			break;

	        case $FIELD_TYPES["multiline_textbox"]:
			if(!$results) $results="&nbsp";
			$results = "<div class='".EIP_mulittextbox($fieldMetaID)."' >".$results."</div>";
			break;
        }

    }
    return $results;

}

function GetProcessedFieldValue($fieldValues, $fieldType, $fieldProperties=array()){
	global $FIELD_TYPES;
	
	$results = array();
	$fieldValues = (array) $fieldValues;
	foreach($fieldValues as $fieldValue){
	
		switch($fieldType){
			case $FIELD_TYPES["audio"]:
			case $FIELD_TYPES["file"]:
			case $FIELD_TYPES["image"]:
				if ($fieldValue != "") $fieldValue = MF_FILES_URI.$fieldValue;
				break;
	
			case $FIELD_TYPES["checkbox"]: 		
				if ($fieldValue == 'true')  $fieldValue = true; else $fieldValue = false; 
				break;
	
			case $FIELD_TYPES["date"]: 
				$fieldValue = date($fieldProperties['format'],strtotime($fieldValue)); 
				break;
		}
		
		array_push($results, $fieldValue); 
	}
	
	// Return array or single value based on field
	switch($fieldType){
		case $FIELD_TYPES["checkbox_list"]:
		case $FIELD_TYPES["listbox"]:
			return $results;
		 	break;
	}

	if (count($results) == 0 )
		return "";
	else
		return $results[0];
}

// Get Audio. 
function get_audio ($fieldName, $groupIndex=1, $fieldIndex=1,$post_id=NULL) {
	require_once("RCCWP_CustomField.php");
	global $wpdb, $post;
	
	if(!$post_id){ $post_id = $post->ID; }
	$field = RCCWP_CustomField::GetInfoByName($fieldName,$post_id);
	if(!$field) return FALSE;
	
	$fieldType = $field['type'];
	$fieldID = $field['id'];
	
	$fieldValues = (array) RCCWP_CustomField::GetValues(true, $post_id, $fieldName, $groupIndex, $fieldIndex);
    if(empty($fieldValues)) return FALSE;
	
	if(!empty($fieldValues))
		$fieldValue = $fieldValues[0];
	else 
		return "";
		
	$path = MF_FILES_URI;
	$fieldValue = $path.$fieldValue;
	$finalString = stripslashes(trim("\<div style=\'padding-top:3px;\'\>\<object classid=\'clsid:D27CDB6E-AE6D-11cf-96B8-444553540000\' codebase='\http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=7,0,19,0\' width=\'95%\' height=\'20\' wmode=\'transparent\' \>\<param name=\'movie\' value=\'".MF_URI."js/singlemp3player.swf?file=".urlencode($fieldValue)."\' wmode=\'transparent\' /\>\<param name=\'quality\' value=\'high\' wmode=\'transparent\' /\>\<embed src=\'".MF_URI."js/singlemp3player.swf?file=".urlencode($fieldValue)."' width=\'50\%\' height=\'20\' quality=\'high\' pluginspage=\'http://www.macromedia.com/go/getflashplayer\' type=\'application/x-shockwave-flash\' wmode=\'transparent\' \>\</embed\>\</object\>\</div\>"));
	return $finalString;
}

function GetFieldInfo($customFieldId)
	{
		global $wpdb;
		$sql = "SELECT properties FROM " . MF_TABLE_CUSTOM_FIELD_PROPERTIES  .
			" WHERE custom_field_id = '" . $customFieldId."'";
		$results = $wpdb->get_row($sql);
		//$results->options = unserialize($results->options);
		$results->properties = unserialize($results->properties);
		//$results->default_value = unserialize($results->default_value);
		return $results;
	}
        
function pt(){
    return PHPTHUMB;
}


/**
 * Return a array with the order of a group
 *
 * @param string $groupName 
 */
function getGroupOrder($field_name,$post_id=NULL){
    global $post,$wpdb;

    if(!$post_id){ $post_id = $post->ID; }
    $elements  = $wpdb->get_results("SELECT group_count FROM ".MF_TABLE_POST_META." WHERE post_id = ".$post_id."  AND field_name = '{$field_name}' ORDER BY order_id ASC");
   
    foreach($elements as $element){
       $order[] =  $element->group_count;
    }
     
    return $order;
}

/**
 *  Return a array with the order of a  field
 */
function getFieldOrder($field_name,$group=1,$post_id=NULL){ 
	global $post,$wpdb; 
	
	if(!$post_id){ $post_id = $post->ID; }
	$elements = $wpdb->get_results("SELECT field_count FROM ".MF_TABLE_POST_META." WHERE post_id = ".$post_id." AND field_name = '{$field_name}' AND group_count = {$group} ORDER BY order_id DESC",ARRAY_A);  

	foreach($elements as $element){ 
		$order[] = $element['field_count']; 
	} 

	$order = array_reverse($order); 
 	sort($order); 

	return $order; 
}
/**
 * Return the name of the write panel the current post uses
 * 
 * @param boolean $safe make the return name 'url safe'
 */
function get_panel_name($safe=true)
{
	global $wpdb, $post;

	$panel_id = $wpdb->get_var("SELECT `meta_value` FROM {$wpdb->postmeta} WHERE post_id = ".$post->ID.' AND meta_key = "'.RC_CWP_POST_WRITE_PANEL_ID_META_KEY.'"');
	if( (int) $panel_id == 0 )
		return false;
	
	$panel_name = $wpdb->get_var("SELECT `name` FROM ".MF_TABLE_PANELS." WHERE id = ".$panel_id);
	if( ! $panel_name )
		return false;

	return ($safe) ? sanitize_title_with_dashes($panel_name) : $panel_name;
}

// Get Image. 
function get_image ($fieldName, $groupIndex=1, $fieldIndex=1,$tag_img=1,$post_id=NULL,$override_params=NULL) {
	return create_image(array(
		'fieldName' => $fieldName, 
		'groupIndex' => $groupIndex, 
		'fieldIndex' => $fieldIndex,
		'param' => $override_params,
		'post_id' => $post_id,
		'tag_img' => (boolean) $tag_img
	));
}

// generate image
function gen_image ($fieldName, $groupIndex=1, $fieldIndex=1,$param=NULL,$attr=NULL,$post_id=NULL) {
	return create_image(array(
		'fieldName' => $fieldName, 
		'groupIndex' => $groupIndex, 
		'fieldIndex' => $fieldIndex,
		'param' => $param,
		'attr' => $attr,
		'post_id' => $post_id
	));
}

/*
 * Generate an image from a field value
 *
 * Accepts a single options, an array of settings. 
 * These are the parameteres it supports:
 *
 *   'fieldName' => (string) the name of the field which holds the image value, 
 *   'groupIndex' => (int) which group set to display, 
 *   'fieldIndex' => (int) which field set to display,
 *   'param' => (string) a html parameter string to use with PHPThumb for the image,
 *   'attr' => (array) an array of extra attributes and values for the image tag,
 *   'post_id' => (int) a specific post id to fetch,
 *   'tag_img' => (boolean) a flag to determine if an img tag should be created, or just return the link to the image file
 *
 */
function create_image($options)
{
	require_once("RCCWP_CustomField.php");
	global $wpdb, $post;
	
	// establish the default values, then override them with 
	// whatever the user has passed in
	$options = array_merge(array(
		// the default options
		'fieldName' => '', 
		'groupIndex' => 1, 
		'fieldIndex' => 1,
		'param' => NULL,
		'attr' => NULL,
		'post_id' => NULL,
		'tag_img' => true
	), (array) $options);
	
	// finally extract them into variables for this function
	extract($options);
	
	// check for a specified post id, or see if the $post global has one
	if(!$post_id && isset($post->ID)){ 
		$post_id = $post->ID; 
	} else {
		return false;
	}
	
	// basic check
	if(empty($fieldName)) return FALSE;
	
	$field = RCCWP_CustomField::GetInfoByName($fieldName,$post_id);
	if(!$field) return FALSE;
	
	$fieldType = $field['type'];
	$fieldID = $field['id'];
	$fieldCSS = $field['CSS'];
	$fieldObject = $field['properties'];
	
	$fieldValues = (array) RCCWP_CustomField::GetValues(true, $post_id, $fieldName, $groupIndex, $fieldIndex);
	if(empty($fieldValues)) return FALSE;

	if(!empty($fieldValues[0]))
		$fieldValue = $fieldValues[0];
	else 
		return "";
	
	// override the default phpthumb parameters if needed
	if(!empty($param)) {
		$fieldObject['params'] = $param;
	}
	
	// remove the ? on the params if it happened to be there
	if (substr($fieldObject['params'], 0, 1) == "?"){
		$fieldObject['params'] = substr($fieldObject['params'], 1);
	}

	// check if exist params, if not exist params, return original image
	if (empty($fieldObject['params']) && (FALSE === strstr($fieldValue, "&"))){
		$fieldValue = MF_FILES_URI.$fieldValue;
	}else{
		//check if exist thumb image, if exist return thumb image
		$md5_params = md5($fieldObject['params']);
		if (file_exists(MF_FILES_PATH.'th_'.$md5_params."_".$fieldValue)) {
			$fieldValue = MF_FILES_URI.'th_'.$md5_params."_".$fieldValue;
		}else{
			//generate thumb
			include_once(dirname(__FILE__)."/thirdparty/phpthumb/phpthumb.class.php");
			$phpThumb = new phpThumb();
			$phpThumb->setSourceFilename(MF_FILES_PATH.$fieldValue);
			$create_md5_filename = 'th_'.$md5_params."_".$fieldValue;
			$output_filename = MF_FILES_PATH.$create_md5_filename;
			$final_filename = MF_FILES_URI.$create_md5_filename;

			$params_image = explode("&",$fieldObject['params']);
			foreach($params_image as $param){
				if($param){
					$p_image=explode("=",$param);
					$phpThumb->setParameter($p_image[0], $p_image[1]);
				}
			}
			if ($phpThumb->GenerateThumbnail()) {
				if ($phpThumb->RenderToFile($output_filename)) {
					$fieldValue = $final_filename;
				}
			}
		}
	}
	
	if($tag_img){
		// make sure the attributes are an array
		if( !is_array($attr) ) $attr = (array) $attr;
		
		// we're generating an image tag, but there MAY be a default class. 
		// if one was defined, however, override it
		if( !isset($attr['class']) && !empty($fieldCSS) ) 
			$attr['class'] = $fieldCSS;
		
		// ok, put it together now
		if(count($attr)){
			foreach($attr as $k => $v){
				$add_attr .= $k."='".$v."' ";
			}
			$finalString = "<img src='".$fieldValue."' ".$add_attr." />";
		}else{
			$finalString = "<img src='".$fieldValue."' />";
	    }
	}else{
		$finalString = $fieldValue;
	}
	return $finalString;
}

?>