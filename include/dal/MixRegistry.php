<?php
$dalTablemixregistry = array();
$dalTablemixregistry["MixNo"] = array("type"=>3,"varname"=>"MixNo");
$dalTablemixregistry["MixColor"] = array("type"=>200,"varname"=>"MixColor");
$dalTablemixregistry["ProdGrpCode"] = array("type"=>200,"varname"=>"ProdGrpCode");
$dalTablemixregistry["Notes"] = array("type"=>200,"varname"=>"Notes");
$dalTablemixregistry["Status"] = array("type"=>200,"varname"=>"Status");
	$dalTablemixregistry["MixNo"]["key"]=true;
	$dalTablemixregistry["ProdGrpCode"]["key"]=true;
$dal_info["mixregistry"]=&$dalTablemixregistry;

?>