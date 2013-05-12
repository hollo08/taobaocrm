<?php
/*********************************************************************************
 * The contents of this file are subject to the SugarCRM Public License Version 1.1.2
 * ("License"); You may not use this file except in compliance with the
 * License. You may obtain a copy of the License at http://www.sugarcrm.com/SPL
 * Software distributed under the License is distributed on an  "AS IS"  basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License for
 * the specific language governing rights and limitations under the License.
 * The Original Code is:  SugarCRM Open Source
 * The Initial Developer of the Original Code is SugarCRM, Inc.
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.;
 * All Rights Reserved.
 * Contributor(s): ______________________________________.
 ********************************************************************************/
require_once('include/CRMSmarty.php');
require_once("data/Tracker.php");
require_once('themes/'.$theme.'/layout_utils.php');
require_once('include/logging.php');
require_once('include/ListView/ListView.php');
require_once('include/database/PearDatabase.php');
require_once('include/ComboUtil.php');
require_once('include/utils/utils.php');
require_once('modules/CustomView/CustomView.php');
require_once('modules/Qunfas/ModuleConfig.php');
require_once('include/DatabaseUtil.php');

global $app_strings;
global $currentModule;
global $theme;
$url_string = '';
$smarty = new CRMSmarty();
if (!isset($where)) $where = "";
//<<<<cutomview>>>>>>>
$oCustomView = new CustomView($currentModule);
$viewid = $oCustomView->getViewId($currentModule);
$customviewcombo_html = $oCustomView->getCustomViewCombo($viewid);
$viewnamedesc = $oCustomView->getCustomViewByCvid($viewid);
//<<<<<customview>>>>>

$popuptype = '';
$popuptype = $_REQUEST["popuptype"];
require_once("modules/$currentModule/$currentModule.php");
$focus = new $currentModule();
$single_module = substr($currentModule,0,(strlen($currentModule)-1));
$smarty->assign("SINGLE_MOD",$single_module);
if(isset($_REQUEST['return_module']) && $_REQUEST['return_module'] !='')
$smarty->assign("RETURN_MODULE",$_REQUEST['return_module']);
$alphabetical = AlphabeticalSearch($currentModule,'Popup',strtolower($single_module).'name','true','basic',$popuptype,"","","",$viewid);
if (isset($_REQUEST['select'])) $smarty->assign("SELECT",'enable');
if(isset($_REQUEST['return_action'])) {
	$return_action = $_REQUEST['return_action'];
	$smarty->assign("RETURN_ACTION",$return_action);
}

$theme_path="themes/".$theme."/";
$image_path=$theme_path."images/";
$smarty->assign("MOD", $mod_strings);
$smarty->assign("APP", $app_strings);
$smarty->assign("IMAGE_PATH",$image_path);
$smarty->assign("THEME_PATH",$theme_path);
$smarty->assign("MODULE",$currentModule);
$smarty->assign("CUSTOMVIEW_OPTION",$customviewcombo_html);
$smarty->assign("VIEWID", $viewid);


//Retreive the list from Database
$query = "";
$recordid = "";
if(isset($_REQUEST['recordid']) && $_REQUEST['recordid'] != '')
{		
	$recordid = $_REQUEST['recordid'];
	$smarty->assign("RECORDID",$recordid);
	$url_string .='&recordid='.$recordid;
}
$return_module = "";
if(isset($_REQUEST['return_module'])) {
	$return_module = $_REQUEST['return_module'];
}
$where_relquery = getRelCheckquery($currentModule,$return_module,$recordid);
//if((!isset($is_disable_approve) || (isset($is_disable_approve) && !$is_disable_approve)) && (isset($module_enable_approve) && $module_enable_approve)) {
//	$where_relquery .= ' AND ec_crmentity.approved=1 ';
//}

if(empty($query)) {
	$query = $focus->getListQuery($where_relquery,true);//viewscope = all_to_me
}
//customview begin
if($viewid != "0")
{
	$query = $oCustomView->getModifiedCvListQuery($viewid,$query,$currentModule,true);
}
//customview end

if(isset($_REQUEST['query']) && $_REQUEST['query'] == 'true')
{
	list($where, $ustring) = split("#@@#",getWhereCondition($currentModule));
	$url_string .="&query=true".$ustring;
}

if(isset($where) && $where != '')
{
        $query .= ' and '.$where;
}

$upperModule = strtoupper($currentModule);
if (isset($_REQUEST['order_by']) && $_REQUEST['order_by'] != '') {
	$order_by = $_REQUEST['order_by'];
}
else {
	$order_by = (($_SESSION[$upperModule.'_ORDER_BY'] != '')?($_SESSION[$upperModule.'_ORDER_BY']):"modifiedtime");
}

if(isset($_REQUEST['sorder']) && $_REQUEST['sorder'] != '') {
	$sorder = $_REQUEST['sorder'];
} else {
	$sorder = (($_SESSION[$upperModule.'_SORT_ORDER'] != '')?($_SESSION[$upperModule.'_SORT_ORDER']):'DESC');
}
$_SESSION[$upperModule.'_ORDER_BY'] = $order_by;
$_SESSION[$upperModule.'_SORT_ORDER'] = $sorder;

if(!isset($order_by) || empty($order_by))
{
	$order_by = 'modifiedtime';
	$sorder = "DESC";
}
//Retreiving the start value from request
if(isset($_REQUEST['start']) && $_REQUEST['start'] != '')
{
        $start = $_REQUEST['start'];
}
else
{

        $start = 1;
}
//Retreiving the no of rows
$count_result = $adb->query(mkCountQuery($query));
$noofrows = $adb->query_result($count_result,0,"count");
//Retreive the Navigation array
$navigation_array = getNavigationValues($start, $noofrows, $list_max_entries_per_page);
$start_rec = $navigation_array['start'];
$end_rec = $navigation_array['end_val'];
if($start_rec != 0)
	$record_string= $app_strings['LBL_SHOWING']." " .$start_rec." - ".$end_rec." " .$app_strings['LBL_LIST_OF'] ." ".$noofrows;

if ($start_rec == 0) 
	$limit_start_rec = 0;
else
	$limit_start_rec = $start_rec -1;
$query_order_by = "";
if(isset($order_by) && $order_by != '')
{	
	if($order_by == 'smownerid')
    {
		$query_order_by = 'user_name';
    }
	else
	{
		$query_order_by =  $focus->entity_table.".".$order_by;
    }
}
$list_result = $adb->limitQuery2($query,$limit_start_rec,$list_max_entries_per_page,$query_order_by,$sorder);
//Retreive the List View Table Header

$focus->list_mode="search";
$focus->popup_type=$popuptype;
$url_string .='&popuptype='.$popuptype;
if(isset($_REQUEST['select']) && $_REQUEST['select'] == 'enable')
	$url_string .='&select=enable';
if(isset($_REQUEST['return_module']) && $_REQUEST['return_module'] != '')
	$url_string .='&return_module='.$_REQUEST['return_module'];

$smarty->assign("URLSTRING", $url_string);

if($viewid != '') 
{
	$url_string .= "&viewname=".$viewid;
}
$listview_header_search=getSearchListHeaderValues($focus,$currentModule,$url_string,$sorder,$order_by);
$smarty->assign("SEARCHLISTHEADER", $listview_header_search);

$smarty->assign("ALPHABETICAL", $alphabetical);


$listview_header = getSearchListViewHeader($focus,$currentModule,$url_string,$sorder,$order_by);
$smarty->assign("LISTHEADER", $listview_header);


$listview_entries = getSearchListViewEntries($focus,$currentModule,$list_result,$navigation_array);
$smarty->assign("LISTENTITY", $listview_entries);

$navigationOutput = getTableHeaderNavigation($navigation_array, $url_string,$currentModule,"Popup",$viewid);
$smarty->assign("NAVIGATION", $navigationOutput);
$smarty->assign("RECORD_COUNTS", $record_string);
$smarty->assign("POPUPTYPE", $popuptype);


if(isset($_REQUEST['ajax']) && $_REQUEST['ajax'] != '')
	$smarty->display("PopupContents.tpl");
else
	$smarty->display("Popup.tpl");

?>

