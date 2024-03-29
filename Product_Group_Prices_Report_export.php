<?php 
@ini_set("display_errors","1");
@ini_set("display_startup_errors","1");
include("include/dbcommon.php");
include("classes/searchclause.php");
session_cache_limiter("none");

include("include/Product_Group_Prices_Report_variables.php");

CheckPermissionsEvent($strTableName, 'P');

$layout = new TLayout("export","FancyOrange","MobileOrange");
$layout->blocks["top"] = array();
$layout->containers["export"] = array();

$layout->containers["export"][] = array("name"=>"exportheader","block"=>"","substyle"=>2);


$layout->containers["export"][] = array("name"=>"exprange_header","block"=>"rangeheader_block","substyle"=>3);


$layout->containers["export"][] = array("name"=>"exprange","block"=>"range_block","substyle"=>1);


$layout->containers["export"][] = array("name"=>"expoutput_header","block"=>"","substyle"=>3);


$layout->containers["export"][] = array("name"=>"expoutput","block"=>"","substyle"=>1);


$layout->containers["export"][] = array("name"=>"expbuttons","block"=>"","substyle"=>2);


$layout->skins["export"] = "fields";
$layout->blocks["top"][] = "export";$page_layouts["Product_Group_Prices_Report_export"] = $layout;


// Modify query: remove blob fields from fieldlist.
// Blob fields on an export page are shown using imager.php (for example).
// They don't need to be selected from DB in export.php itself.
//$gQuery->ReplaceFieldsWithDummies(GetBinaryFieldsIndices());

$cipherer = new RunnerCipherer($strTableName);

$strWhereClause = "";
$strHavingClause = "";
$strSearchCriteria = "and";
$selected_recs = array();
$options = "1";

header("Expires: Thu, 01 Jan 1970 00:00:01 GMT"); 
include('include/xtempl.php');
include('classes/runnerpage.php');
$xt = new Xtempl();
$id = postvalue("id") != "" ? postvalue("id") : 1;

$phpVersion = (int)substr(phpversion(), 0, 1); 
if($phpVersion > 4)
{
	include("include/export_functions.php");
	$xt->assign("groupExcel", true);
}
else
	$xt->assign("excel", true);

//array of params for classes
$params = array("pageType" => PAGE_EXPORT, "id" => $id, "tName" => $strTableName);
$params["xt"] = &$xt;
if(!$eventObj->exists("ListGetRowCount") && !$eventObj->exists("ListQuery"))
	$params["needSearchClauseObj"] = false;
$pageObject = new RunnerPage($params);

//	Before Process event
if($eventObj->exists("BeforeProcessExport"))
	$eventObj->BeforeProcessExport($conn, $pageObject);

if (@$_REQUEST["a"]!="")
{
	$options = "";
	$sWhere = "1=0";	

//	process selection
	$selected_recs = array();
	if (@$_REQUEST["mdelete"])
	{
		foreach(@$_REQUEST["mdelete"] as $ind)
		{
			$keys=array();
			$keys["ProdNo"] = refine($_REQUEST["mdelete1"][mdeleteIndex($ind)]);
			$selected_recs[] = $keys;
		}
	}
	elseif(@$_REQUEST["selection"])
	{
		foreach(@$_REQUEST["selection"] as $keyblock)
		{
			$arr=explode("&",refine($keyblock));
			if(count($arr)<1)
				continue;
			$keys = array();
			$keys["ProdNo"] = urldecode($arr[0]);
			$selected_recs[] = $keys;
		}
	}

	foreach($selected_recs as $keys)
	{
		$sWhere = $sWhere . " or ";
		$sWhere.=KeyWhere($keys);
	}


	$strSQL = $gQuery->gSQLWhere($sWhere);
	$strWhereClause=$sWhere;
	
	$_SESSION[$strTableName."_SelectedSQL"] = $strSQL;
	$_SESSION[$strTableName."_SelectedWhere"] = $sWhere;
	$_SESSION[$strTableName."_SelectedRecords"] = $selected_recs;
}

if ($_SESSION[$strTableName."_SelectedSQL"]!="" && @$_REQUEST["records"]=="") 
{
	$strSQL = $_SESSION[$strTableName."_SelectedSQL"];
	$strWhereClause = @$_SESSION[$strTableName."_SelectedWhere"];
	$selected_recs = $_SESSION[$strTableName."_SelectedRecords"];
}
else
{
	$strWhereClause = @$_SESSION[$strTableName."_where"];
	$strHavingClause = @$_SESSION[$strTableName."_having"];
	$strSearchCriteria = @$_SESSION[$strTableName."_criteria"];
	$strSQL = $gQuery->gSQLWhere($strWhereClause, $strHavingClause, $strSearchCriteria);
}

$mypage = 1;
if(@$_REQUEST["type"])
{
//	order by
	$strOrderBy = $_SESSION[$strTableName."_order"];
	if(!$strOrderBy)
		$strOrderBy = $gstrOrderBy;
	$strSQL.=" ".trim($strOrderBy);

	$strSQLbak = $strSQL;
	if($eventObj->exists("BeforeQueryExport"))
		$eventObj->BeforeQueryExport($strSQL,$strWhereClause,$strOrderBy, $pageObject);
//	Rebuild SQL if needed
	if($strSQL!=$strSQLbak)
	{
//	changed $strSQL - old style	
		$numrows=GetRowCount($strSQL);
	}
	else
	{
		$strSQL = $gQuery->gSQLWhere($strWhereClause, $strHavingClause, $strSearchCriteria);
		$strSQL.=" ".trim($strOrderBy);
		$rowcount=false;
		if($eventObj->exists("ListGetRowCount"))
		{
			$masterKeysReq=array();
			for($i = 0; $i < count($pageObject->detailKeysByM); $i ++)
				$masterKeysReq[] = $_SESSION[$strTableName."_masterkey".($i + 1)];
			$rowcount = $eventObj->ListGetRowCount($pageObject->searchClauseObj,$_SESSION[$strTableName."_mastertable"],$masterKeysReq,$selected_recs, $pageObject);
		}
		if($rowcount !== false)
			$numrows = $rowcount;
		else
			$numrows = $gQuery->gSQLRowCount($strWhereClause,$strHavingClause,$strSearchCriteria);
	}
	LogInfo($strSQL);

//	 Pagination:

	$nPageSize = 0;
	if(@$_REQUEST["records"]=="page" && $numrows)
	{
		$mypage = (integer)@$_SESSION[$strTableName."_pagenumber"];
		$nPageSize = (integer)@$_SESSION[$strTableName."_pagesize"];
		
		if(!$nPageSize)
			$nPageSize = $gSettings->getInitialPageSize();
				
		if($nPageSize<0)
			$nPageSize = 0;
			
		if($nPageSize>0)
		{
			if($numrows<=($mypage-1)*$nPageSize)
				$mypage = ceil($numrows/$nPageSize);
		
			if(!$mypage)
				$mypage = 1;
			
					$strSQL.=" limit ".(($mypage-1)*$nPageSize).",".$nPageSize;
		}
	}
	$listarray = false;
	if($eventObj->exists("ListQuery"))
	{
		$arrFieldForSort = array();
		$arrHowFieldSort = array();
		require_once getabspath('classes/orderclause.php');
		$fieldList = unserialize($_SESSION[$strTableName."_orderFieldsList"]);
		for($i = 0; $i < count($fieldList); $i++)
		{
			$arrFieldForSort[] = $fieldList[$i]->fieldIndex; 
			$arrHowFieldSort[] = $fieldList[$i]->orderDirection; 
		}
		$listarray = $eventObj->ListQuery($pageObject->searchClauseObj, $arrFieldForSort, $arrHowFieldSort,
			$_SESSION[$strTableName."_mastertable"], $masterKeysReq, $selected_recs, $nPageSize, $mypage, $pageObject);
	}
	if($listarray!==false)
		$rs = $listarray;
	elseif($nPageSize>0)
	{
					$rs = db_query($strSQL,$conn);
	}
	else
		$rs = db_query($strSQL,$conn);

	if(!ini_get("safe_mode"))
		set_time_limit(300);
	
	if(substr(@$_REQUEST["type"],0,5)=="excel")
	{
//	remove grouping
		$locale_info["LOCALE_SGROUPING"]="0";
		$locale_info["LOCALE_SMONGROUPING"]="0";
				if($phpVersion > 4)
			ExportToExcel($cipherer, $pageObject);
		else
			ExportToExcel_old($cipherer);
	}
	else if(@$_REQUEST["type"]=="word")
	{
		ExportToWord($cipherer);
	}
	else if(@$_REQUEST["type"]=="xml")
	{
		ExportToXML($cipherer);
	}
	else if(@$_REQUEST["type"]=="csv")
	{
		$locale_info["LOCALE_SGROUPING"]="0";
		$locale_info["LOCALE_SDECIMAL"]=".";
		$locale_info["LOCALE_SMONGROUPING"]="0";
		$locale_info["LOCALE_SMONDECIMALSEP"]=".";
		ExportToCSV($cipherer);
	}
	db_close($conn);
	return;
}

// add button events if exist
$pageObject->addButtonHandlers();

if($options)
{
	$xt->assign("rangeheader_block",true);
	$xt->assign("range_block",true);
}

$xt->assign("exportlink_attrs", 'id="saveButton'.$pageObject->id.'"');

$pageObject->body["begin"] .="<script type=\"text/javascript\" src=\"include/loadfirst.js\"></script>\r\n";
$pageObject->body["begin"] .= "<script type=\"text/javascript\" src=\"include/lang/".getLangFileName(mlang_getcurrentlang()).".js\"></script>";

$pageObject->fillSetCntrlMaps();
$pageObject->body['end'] .= '<script>';
$pageObject->body['end'] .= "window.controlsMap = ".my_json_encode($pageObject->controlsHTMLMap).";";
$pageObject->body['end'] .= "window.viewControlsMap = ".my_json_encode($pageObject->viewControlsHTMLMap).";";
$pageObject->body['end'] .= "window.settings = ".my_json_encode($pageObject->jsSettings).";";
$pageObject->body['end'] .= '</script>';
$pageObject->body["end"] .= "<script language=\"JavaScript\" src=\"include/runnerJS/RunnerAll.js\"></script>\r\n";
$pageObject->addCommonJs();

$pageObject->body["end"] .= "<script>".$pageObject->PrepareJS()."</script>";
$xt->assignbyref("body",$pageObject->body);

$xt->display("Product_Group_Prices_Report_export.htm");

function ExportToExcel_old($cipherer)
{
	global $cCharset;
	header("Content-Type: application/vnd.ms-excel");
	header("Content-Disposition: attachment;Filename=Product_Group_Prices_Report.xls");

	echo "<html>";
	echo "<html xmlns:o=\"urn:schemas-microsoft-com:office:office\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns=\"http://www.w3.org/TR/REC-html40\">";
	
	echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=".$cCharset."\">";
	echo "<body>";
	echo "<table border=1>";

	WriteTableData($cipherer);

	echo "</table>";
	echo "</body>";
	echo "</html>";
}

function ExportToWord($cipherer)
{
	global $cCharset;
	header("Content-Type: application/vnd.ms-word");
	header("Content-Disposition: attachment;Filename=Product_Group_Prices_Report.doc");

	echo "<html>";
	echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=".$cCharset."\">";
	echo "<body>";
	echo "<table border=1>";

	WriteTableData($cipherer);

	echo "</table>";
	echo "</body>";
	echo "</html>";
}

function ExportToXML($cipherer)
{
	global $nPageSize,$rs,$strTableName,$conn,$eventObj, $pageObject;
	header("Content-Type: text/xml");
	header("Content-Disposition: attachment;Filename=Product_Group_Prices_Report.xml");
	if($eventObj->exists("ListFetchArray"))
		$row = $eventObj->ListFetchArray($rs, $pageObject);
	else
		$row = $cipherer->DecryptFetchedArray($rs);	
	//if(!$row)
	//	return;
		
	global $cCharset;
	
	echo "<?xml version=\"1.0\" encoding=\"".$cCharset."\" standalone=\"yes\"?>\r\n";
	echo "<table>\r\n";
	$i = 0;
	$pageObject->viewControls->forExport = "xml";
	while((!$nPageSize || $i<$nPageSize) && $row)
	{
		$values = array();
			$values["ProdGrpCode"] = $pageObject->showDBValue("ProdGrpCode", $row);
			$values["ProdThaiName"] = $pageObject->showDBValue("ProdThaiName", $row);
			$values["ProdEngName"] = $pageObject->showDBValue("ProdEngName", $row);
			$values["Picture"] = $pageObject->showDBValue("Picture", $row);
			$values["ThaiModel"] = $pageObject->showDBValue("ThaiModel", $row);
			$values["EngModel"] = $pageObject->showDBValue("EngModel", $row);
			$values["MixNo"] = $pageObject->showDBValue("MixNo", $row);
			$values["ProdWt"] = $pageObject->showDBValue("ProdWt", $row);
			$values["ColorMixNo"] = $pageObject->showDBValue("ColorMixNo", $row);
			$values["FaceWt"] = $pageObject->showDBValue("FaceWt", $row);
			$values["Mold"] = $pageObject->showDBValue("Mold", $row);
			$values["PiecesPerMould"] = $pageObject->showDBValue("PiecesPerMould", $row);
			$values["ProductCost"] = $pageObject->showDBValue("ProductCost", $row);
			$values["PriceRetail"] = $pageObject->showDBValue("PriceRetail", $row);
			$values["PriceWholeSale"] = $pageObject->showDBValue("PriceWholeSale", $row);
			$values["ProdStockGoal"] = $pageObject->showDBValue("ProdStockGoal", $row);
			$values["ThaiCom"] = $pageObject->showDBValue("ThaiCom", $row);
			$values["EngCom"] = $pageObject->showDBValue("EngCom", $row);
			$values["Status"] = $pageObject->showDBValue("Status", $row);
			$values["MoldWt"] = $pageObject->showDBValue("MoldWt", $row);
			$values["GrayWtMold"] = $pageObject->showDBValue("GrayWtMold", $row);
			$values["ColorWtMold"] = $pageObject->showDBValue("ColorWtMold", $row);
			$values["Length"] = $pageObject->showDBValue("Length", $row);
			$values["Width1"] = $pageObject->showDBValue("Width1", $row);
			$values["Thickness"] = $pageObject->showDBValue("Thickness", $row);
			$values["ProdNo"] = $pageObject->showDBValue("ProdNo", $row);
		
		$eventRes = true;
		if ($eventObj->exists('BeforeOut'))
			$eventRes = $eventObj->BeforeOut($row, $values, $pageObject);
		
		if ($eventRes)
		{
			$i++;
			echo "<row>\r\n";
			foreach ($values as $fName => $val)
			{
				$field = htmlspecialchars(XMLNameEncode($fName));
				echo "<".$field.">";
				echo $values[$fName];
				echo "</".$field.">\r\n";
			}
			echo "</row>\r\n";
		}
		
		
		if($eventObj->exists("ListFetchArray"))
			$row = $eventObj->ListFetchArray($rs, $pageObject);
		else
			$row = $cipherer->DecryptFetchedArray($rs);
	}
	echo "</table>\r\n";
}

function ExportToCSV($cipherer)
{
	global $rs,$nPageSize,$strTableName,$conn,$eventObj, $pageObject;
	header("Content-Type: application/csv");
	header("Content-Disposition: attachment;Filename=Product_Group_Prices_Report.csv");
	
	if($eventObj->exists("ListFetchArray"))
		$row = $eventObj->ListFetchArray($rs, $pageObject);
	else
		$row = $cipherer->DecryptFetchedArray($rs);

// write header
	$outstr = "";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"ProdGrpCode\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"ProdThaiName\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"ProdEngName\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"Picture\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"ThaiModel\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"EngModel\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"MixNo\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"ProdWt\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"ColorMixNo\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"FaceWt\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"Mold\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"PiecesPerMould\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"ProductCost\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"PriceRetail\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"PriceWholeSale\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"ProdStockGoal\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"ThaiCom\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"EngCom\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"Status\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"MoldWt\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"GrayWtMold\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"ColorWtMold\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"Length\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"Width1\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"Thickness\"";
	if($outstr!="")
		$outstr.=",";
	$outstr.= "\"ProdNo\"";
	echo $outstr;
	echo "\r\n";

// write data rows
	$iNumberOfRows = 0;
	$pageObject->viewControls->forExport = "csv";
	while((!$nPageSize || $iNumberOfRows < $nPageSize) && $row)
	{
		$values = array();
			$values["ProdGrpCode"] = $pageObject->getViewControl("ProdGrpCode")->showDBValue($row, "");
			$values["ProdThaiName"] = $pageObject->getViewControl("ProdThaiName")->showDBValue($row, "");
			$values["ProdEngName"] = $pageObject->getViewControl("ProdEngName")->showDBValue($row, "");
			$values["Picture"] = $pageObject->getViewControl("Picture")->showDBValue($row, "");
			$values["ThaiModel"] = $pageObject->getViewControl("ThaiModel")->showDBValue($row, "");
			$values["EngModel"] = $pageObject->getViewControl("EngModel")->showDBValue($row, "");
			$values["MixNo"] = $pageObject->getViewControl("MixNo")->showDBValue($row, "");
			$values["ProdWt"] = $row["ProdWt"];
			$values["ColorMixNo"] = $pageObject->getViewControl("ColorMixNo")->showDBValue($row, "");
			$values["FaceWt"] = $row["FaceWt"];
			$values["Mold"] = $row["Mold"];
			$values["PiecesPerMould"] = $pageObject->getViewControl("PiecesPerMould")->showDBValue($row, "");
			$values["ProductCost"] = $row["ProductCost"];
			$values["PriceRetail"] = $row["PriceRetail"];
			$values["PriceWholeSale"] = $row["PriceWholeSale"];
			$values["ProdStockGoal"] = $pageObject->getViewControl("ProdStockGoal")->showDBValue($row, "");
			$values["ThaiCom"] = $pageObject->getViewControl("ThaiCom")->showDBValue($row, "");
			$values["EngCom"] = $pageObject->getViewControl("EngCom")->showDBValue($row, "");
			$values["Status"] = $pageObject->getViewControl("Status")->showDBValue($row, "");
			$values["MoldWt"] = $row["MoldWt"];
			$values["GrayWtMold"] = $row["GrayWtMold"];
			$values["ColorWtMold"] = $row["ColorWtMold"];
			$values["Length"] = $row["Length"];
			$values["Width1"] = $row["Width1"];
			$values["Thickness"] = $row["Thickness"];
			$values["ProdNo"] = $pageObject->getViewControl("ProdNo")->showDBValue($row, "");

		$eventRes = true;
		if ($eventObj->exists('BeforeOut'))
		{
			$eventRes = $eventObj->BeforeOut($row,$values, $pageObject);
		}
		if ($eventRes)
		{
			$outstr="";
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["ProdGrpCode"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["ProdThaiName"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["ProdEngName"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["Picture"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["ThaiModel"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["EngModel"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["MixNo"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["ProdWt"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["ColorMixNo"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["FaceWt"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["Mold"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["PiecesPerMould"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["ProductCost"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["PriceRetail"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["PriceWholeSale"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["ProdStockGoal"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["ThaiCom"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["EngCom"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["Status"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["MoldWt"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["GrayWtMold"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["ColorWtMold"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["Length"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["Width1"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["Thickness"]).'"';
			if($outstr!="")
				$outstr.=",";
			$outstr.='"'.str_replace('"', '""', $values["ProdNo"]).'"';
			echo $outstr;
		}
		
		$iNumberOfRows++;
		if($eventObj->exists("ListFetchArray"))
			$row = $eventObj->ListFetchArray($rs, $pageObject);
		else
			$row = $cipherer->DecryptFetchedArray($rs);
			
		if(((!$nPageSize || $iNumberOfRows<$nPageSize) && $row) && $eventRes)
			echo "\r\n";
	}
}

function WriteTableData($cipherer)
{
	global $rs,$nPageSize,$strTableName,$conn,$eventObj, $pageObject;
	
	if($eventObj->exists("ListFetchArray"))
		$row = $eventObj->ListFetchArray($rs, $pageObject);
	else
		$row = $cipherer->DecryptFetchedArray($rs);
//	if(!$row)
//		return;
// write header
	echo "<tr>";
	if($_REQUEST["type"]=="excel")
	{
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Prod Grp Code").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Prod Thai Name").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Prod Eng Name").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Picture").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Thai Model").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Eng Model").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Mix No").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Prod Wt").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Color Mix No").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Face Wt").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Mold").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Pieces Per Mould").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Product Cost").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Price Retail").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Price Whole Sale").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Prod Stock Goal").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Thai Com").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Eng Com").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Status").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Mold Wt").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Gray Wt Mold").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Color Wt Mold").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Length").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Width1").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Thickness").'</td>';	
		echo '<td style="width: 100" x:str>'.PrepareForExcel("Prod No").'</td>';	
	}
	else
	{
		echo "<td>"."Prod Grp Code"."</td>";
		echo "<td>"."Prod Thai Name"."</td>";
		echo "<td>"."Prod Eng Name"."</td>";
		echo "<td>"."Picture"."</td>";
		echo "<td>"."Thai Model"."</td>";
		echo "<td>"."Eng Model"."</td>";
		echo "<td>"."Mix No"."</td>";
		echo "<td>"."Prod Wt"."</td>";
		echo "<td>"."Color Mix No"."</td>";
		echo "<td>"."Face Wt"."</td>";
		echo "<td>"."Mold"."</td>";
		echo "<td>"."Pieces Per Mould"."</td>";
		echo "<td>"."Product Cost"."</td>";
		echo "<td>"."Price Retail"."</td>";
		echo "<td>"."Price Whole Sale"."</td>";
		echo "<td>"."Prod Stock Goal"."</td>";
		echo "<td>"."Thai Com"."</td>";
		echo "<td>"."Eng Com"."</td>";
		echo "<td>"."Status"."</td>";
		echo "<td>"."Mold Wt"."</td>";
		echo "<td>"."Gray Wt Mold"."</td>";
		echo "<td>"."Color Wt Mold"."</td>";
		echo "<td>"."Length"."</td>";
		echo "<td>"."Width1"."</td>";
		echo "<td>"."Thickness"."</td>";
		echo "<td>"."Prod No"."</td>";
	}
	echo "</tr>";
	
// write data rows
	$iNumberOfRows = 0;
	$pageObject->viewControls->forExport = "export";
	while((!$nPageSize || $iNumberOfRows<$nPageSize) && $row)
	{
		countTotals($totals, $totalsFields, $row);
		
		$values = array();
	
					$values["ProdGrpCode"] = $pageObject->getViewControl("ProdGrpCode")->showDBValue($row, "");
					$values["ProdThaiName"] = $pageObject->getViewControl("ProdThaiName")->showDBValue($row, "");
					$values["ProdEngName"] = $pageObject->getViewControl("ProdEngName")->showDBValue($row, "");
					$values["Picture"] = $pageObject->getViewControl("Picture")->showDBValue($row, "");
					$values["ThaiModel"] = $pageObject->getViewControl("ThaiModel")->showDBValue($row, "");
					$values["EngModel"] = $pageObject->getViewControl("EngModel")->showDBValue($row, "");
					$values["MixNo"] = $pageObject->getViewControl("MixNo")->showDBValue($row, "");
					$values["ProdWt"] = $pageObject->getViewControl("ProdWt")->showDBValue($row, "");
					$values["ColorMixNo"] = $pageObject->getViewControl("ColorMixNo")->showDBValue($row, "");
					$values["FaceWt"] = $pageObject->getViewControl("FaceWt")->showDBValue($row, "");
					$values["Mold"] = $pageObject->getViewControl("Mold")->showDBValue($row, "");
					$values["PiecesPerMould"] = $pageObject->getViewControl("PiecesPerMould")->showDBValue($row, "");
					$values["ProductCost"] = $pageObject->getViewControl("ProductCost")->showDBValue($row, "");
					$values["PriceRetail"] = $pageObject->getViewControl("PriceRetail")->showDBValue($row, "");
					$values["PriceWholeSale"] = $pageObject->getViewControl("PriceWholeSale")->showDBValue($row, "");
					$values["ProdStockGoal"] = $pageObject->getViewControl("ProdStockGoal")->showDBValue($row, "");
					$values["ThaiCom"] = $pageObject->getViewControl("ThaiCom")->showDBValue($row, "");
					$values["EngCom"] = $pageObject->getViewControl("EngCom")->showDBValue($row, "");
					$values["Status"] = $pageObject->getViewControl("Status")->showDBValue($row, "");
					$values["MoldWt"] = $pageObject->getViewControl("MoldWt")->showDBValue($row, "");
					$values["GrayWtMold"] = $pageObject->getViewControl("GrayWtMold")->showDBValue($row, "");
					$values["ColorWtMold"] = $pageObject->getViewControl("ColorWtMold")->showDBValue($row, "");
					$values["Length"] = $pageObject->getViewControl("Length")->showDBValue($row, "");
					$values["Width1"] = $pageObject->getViewControl("Width1")->showDBValue($row, "");
					$values["Thickness"] = $pageObject->getViewControl("Thickness")->showDBValue($row, "");
					$values["ProdNo"] = $pageObject->getViewControl("ProdNo")->showDBValue($row, "");
		
		$eventRes = true;
		if ($eventObj->exists('BeforeOut'))
		{
			$eventRes = $eventObj->BeforeOut($row, $values, $pageObject);
		}
		if ($eventRes)
		{
			$iNumberOfRows++;
			echo "<tr>";
		
							if($_REQUEST["type"]=="excel")
					echo '<td x:str>';
				else
					echo '<td>';
			
									if($_REQUEST["type"]=="excel")
						echo PrepareForExcel($values["ProdGrpCode"]);
					else
						echo $values["ProdGrpCode"];
			echo '</td>';
							if($_REQUEST["type"]=="excel")
					echo '<td x:str>';
				else
					echo '<td>';
			
									if($_REQUEST["type"]=="excel")
						echo PrepareForExcel($values["ProdThaiName"]);
					else
						echo $values["ProdThaiName"];
			echo '</td>';
							if($_REQUEST["type"]=="excel")
					echo '<td x:str>';
				else
					echo '<td>';
			
									if($_REQUEST["type"]=="excel")
						echo PrepareForExcel($values["ProdEngName"]);
					else
						echo $values["ProdEngName"];
			echo '</td>';
							if($_REQUEST["type"]=="excel")
					echo '<td x:str>';
				else
					echo '<td>';
			
									if($_REQUEST["type"]=="excel")
						echo PrepareForExcel($values["Picture"]);
					else
						echo $values["Picture"];
			echo '</td>';
							if($_REQUEST["type"]=="excel")
					echo '<td x:str>';
				else
					echo '<td>';
			
									if($_REQUEST["type"]=="excel")
						echo PrepareForExcel($values["ThaiModel"]);
					else
						echo $values["ThaiModel"];
			echo '</td>';
							if($_REQUEST["type"]=="excel")
					echo '<td x:str>';
				else
					echo '<td>';
			
									if($_REQUEST["type"]=="excel")
						echo PrepareForExcel($values["EngModel"]);
					else
						echo $values["EngModel"];
			echo '</td>';
							if($_REQUEST["type"]=="excel")
					echo '<td x:str>';
				else
					echo '<td>';
			
									if($_REQUEST["type"]=="excel")
						echo PrepareForExcel($values["MixNo"]);
					else
						echo $values["MixNo"];
			echo '</td>';
							echo '<td>';
			
									echo $values["ProdWt"];
			echo '</td>';
							if($_REQUEST["type"]=="excel")
					echo '<td x:str>';
				else
					echo '<td>';
			
									if($_REQUEST["type"]=="excel")
						echo PrepareForExcel($values["ColorMixNo"]);
					else
						echo $values["ColorMixNo"];
			echo '</td>';
							echo '<td>';
			
									echo $values["FaceWt"];
			echo '</td>';
							echo '<td>';
			
									echo $values["Mold"];
			echo '</td>';
							echo '<td>';
			
									echo $values["PiecesPerMould"];
			echo '</td>';
							echo '<td>';
			
									echo $values["ProductCost"];
			echo '</td>';
							echo '<td>';
			
									echo $values["PriceRetail"];
			echo '</td>';
							echo '<td>';
			
									echo $values["PriceWholeSale"];
			echo '</td>';
							echo '<td>';
			
									echo $values["ProdStockGoal"];
			echo '</td>';
							if($_REQUEST["type"]=="excel")
					echo '<td x:str>';
				else
					echo '<td>';
			
									if($_REQUEST["type"]=="excel")
						echo PrepareForExcel($values["ThaiCom"]);
					else
						echo $values["ThaiCom"];
			echo '</td>';
							if($_REQUEST["type"]=="excel")
					echo '<td x:str>';
				else
					echo '<td>';
			
									if($_REQUEST["type"]=="excel")
						echo PrepareForExcel($values["EngCom"]);
					else
						echo $values["EngCom"];
			echo '</td>';
							if($_REQUEST["type"]=="excel")
					echo '<td x:str>';
				else
					echo '<td>';
			
									if($_REQUEST["type"]=="excel")
						echo PrepareForExcel($values["Status"]);
					else
						echo $values["Status"];
			echo '</td>';
							echo '<td>';
			
									echo $values["MoldWt"];
			echo '</td>';
							echo '<td>';
			
									echo $values["GrayWtMold"];
			echo '</td>';
							echo '<td>';
			
									echo $values["ColorWtMold"];
			echo '</td>';
							echo '<td>';
			
									echo $values["Length"];
			echo '</td>';
							echo '<td>';
			
									echo $values["Width1"];
			echo '</td>';
							echo '<td>';
			
									echo $values["Thickness"];
			echo '</td>';
							echo '<td>';
			
									echo $values["ProdNo"];
			echo '</td>';
			echo "</tr>";
		}
		
		
		if($eventObj->exists("ListFetchArray"))
			$row = $eventObj->ListFetchArray($rs, $pageObject);
		else
			$row = $cipherer->DecryptFetchedArray($rs);
	}
	
}

function XMLNameEncode($strValue)
{
	$search = array(" ","#","'","/","\\","(",")",",","[");
	$ret = str_replace($search,"",$strValue);
	$search = array("]","+","\"","-","_","|","}","{","=");
	$ret = str_replace($search,"",$ret);
	return $ret;
}

function PrepareForExcel($ret)
{
	//$ret = htmlspecialchars($str); commented for bug #6823
	if (substr($ret,0,1)== "=") 
		$ret = "&#61;".substr($ret,1);
	return $ret;

}

function countTotals(&$totals, $totalsFields, $data)
{
	for($i = 0; $i < count($totalsFields); $i ++) 
	{
		if($totalsFields[$i]['totalsType'] == 'COUNT') 
			$totals[$totalsFields[$i]['fName']]["value"] += ($data[$totalsFields[$i]['fName']]!= "");
		else if($totalsFields[$i]['viewFormat'] == "Time") 
		{
			$time = GetTotalsForTime($data[$totalsFields[$i]['fName']]);
			$totals[$totalsFields[$i]['fName']]["value"] += $time[2]+$time[1]*60 + $time[0]*3600;
		} 
		else 
			$totals[$totalsFields[$i]['fName']]["value"] += ($data[$totalsFields[$i]['fName']]+ 0);
		
		if($totalsFields[$i]['totalsType'] == 'AVERAGE')
		{
			if(!is_null($data[$totalsFields[$i]['fName']]) && $data[$totalsFields[$i]['fName']]!=="")
				$totals[$totalsFields[$i]['fName']]['numRows']++;
		}
	}
}
?>
