<?php

class AmazonAPI {

    var $amazon_aff_id;
    var $amazon_access_key;
    var $amazon_secret_key;

    var $url_params;
    var $itemID;
    var $xml;
		var $srch_Keywords;

    var $operation;
    var $signature;
    var $response_groups = "Medium,Images,OfferSummary";

    var $error_message;
    var $error=0;
    

    public function __construct($affid, $access, $secret)
    {
        $this->amazon_aff_id = $affid;
        $this->amazon_access_key = $access;
        $this->amazon_secret_key = $secret;
    }

		public function build_search_url() {
        $url = "http://webservices.amazon.com/onca/xml?";

        //$this->response_groups = str_replace(",", "%2C", $this->response_groups);

        $url_params = "AWSAccessKeyId=" . $this->amazon_access_key;
        $url_params .= "&AssociateTag=" . $this->amazon_aff_id;

        if(!empty($this->itemID)) {
					  $url_params .= "&Keywords=" .  str_replace(" ", "%20","the hunger games");
						//p($url_params,1);
            //$url_params .= "&Keywords=" . "the%20hunger%20games";
						//p($url_params);
        }
//p($this->srch_Keywords, "sds");
        if(!empty($this->srch_Keywords)) {
            $url_params .= "&Keywords=" . $this->srch_Keywords;
        }

        $url_params .= "&Operation=" . $this->operation ."&SearchIndex=All";
        //$url_params .= "&ResponseGroup=" . $this->response_groups;
        $url_params .= "&Service=AWSECommerceService";
       // $url_params .= "&ItemPage=4";
        $url_params .= "&Timestamp=" . rawurlencode(gmdate("Y-m-d\TH:i:s\Z"));
        $url_params .= "&Version=2013-08-01";

        $this->url_params = $url_params;

        $url .= $url_params;
        $url .= "&Signature=" . $this->generate_signature();
//p($url);
        return $url;
		
		}
    public function build_url()
    {
        $url = "http://webservices.amazon.com/onca/xml?";

        $this->response_groups = str_replace(",", "%2C", $this->response_groups);

        $url_params = "AWSAccessKeyId=" . $this->amazon_access_key;
        $url_params .= "&AssociateTag=" . $this->amazon_aff_id;

        if(!empty($this->itemID)) {
            $url_params .= "&ItemId=" . $this->itemID;
        }

        if(!empty($this->keywords)) {
            $url_params .= "&keywords=" . str_replace(",", "%2C", $this->keywords);
        }

        $url_params .= "&Operation=" . $this->operation;
        $url_params .= "&ResponseGroup=" . $this->response_groups;
        $url_params .= "&Service=AWSECommerceService";
        $url_params .= "&Timestamp=" . rawurlencode(gmdate("Y-m-d\TH:i:s\Z"));
        $url_params .= "&Version=2013-08-01";

        $this->url_params = $url_params;

        $url .= $url_params;
        $url .= "&Signature=" . $this->generate_signature();

        return $url;
    }

    public function generate_signature()
    {
        $this->signature = base64_encode(hash_hmac("sha256",
            "GET\nwebservices.amazon.com\n/onca/xml\n" . $this->url_params,
            $this->amazon_secret_key, True));
        $this->signature = str_replace("+", "%2B", $this->signature);
        $this->signature = str_replace("=", "%3D", $this->signature);
        return $this->signature;
    }

    public function item_lookup($id)
    {
        $this->operation = "ItemLookup";
        $this->itemID = $id;

        $url = $this->build_url();
        $ch = curl_init();  

        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);

        $output = curl_exec($ch);
        curl_close($ch);

        $this->xml = simplexml_load_string($output);
				/*
				if(isset($this->xml->Items->Request->Errors)) {
					p($this->xml->Items->Request->Errors->Error->Message);
				}
				**/
        return $this;
    }

    public function item_search($srch_Keywords)
    {
			//p($Keywords);
        $this->operation = "ItemSearch";
        $this->srch_Keywords = str_replace(" ", "%2C", $srch_Keywords);
				//p($this->Keywords);

        $url = $this->build_search_url();

        $ch = curl_init();  

        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);

        $output = curl_exec($ch);
				//p($output);
        curl_close($ch);

        $this->xml = simplexml_load_string($output);
        return $this;
    }

    public function check_for_errors()
    {
        if(isset($this->xml->Error)) {
            $this->error_message = $this->xml->Error->Message;
            $this->error = 1;
        }
        if(isset($this->xml->Items->Request->Errors)) {
            $this->error_message = $this->xml->Items->Request->Errors->Error->Message;
            $this->error = 1;
        }
        return $this->error;
    }

    public function get_item_price($product)
    {
        $price = 0;
        if(isset($product->LowestNewPrice)) {
            $price = $product->LowestNewPrice->Amount;
        } elseif(isset($product->LowestUsedPrice)) {
            $price = $product->LowestUsedPrice->Amount;
        } elseif(isset($product->LowestCollectiblePrice)) {
            $price = $product->LowestCollectiblePrice->Amount;
        } elseif(isset($product->LowestRefurbishedPrice)) {
            $price = $product->LowestRefurbishedPrice->Amount;
        }
        return $price;
    }

    public function get_items_data()
    {
        if($this->check_for_errors()) return "<h4>Error:</h4>".$this->error_message;
        $product = $this->xml->Items;
				$json = json_encode($product);
				$arr = json_decode($json);
				$items = $arr->Item;
				
				$asins = array();
				$searchItems = array();
				foreach($items as $item) {
					$asins[] = $item->ASIN;
					$searchItems[] = $this->item_lookup($item->ASIN)->get_item_data("array");
				}
				return htmlspecialchars(json_encode($searchItems), ENT_QUOTES, 'UTF-8');

				p($asins, $searchItems, json_encode($searchItems));
		}

    public function get_item_data($ret="json")
    {
        if($this->check_for_errors()) return "<h4>Error:</h4>".$this->error_message;

        $product = $this->xml->Items->Item;
				$json = json_encode($product);
				$arr = json_decode($json, true);
				//p($arr);
				//p($product);
				//p($this->xml->Items);
        $item = new STDclass;
        $item->detailedPageURL = $product->DetailPageURL;
        $item->link = "https://www.amazon.com/gp/product/".$this->itemID."/?tag=" . $this->amazon_aff_id;
        $item->title = $product->ItemAttributes->Title;
        $item->smallImage = $product->SmallImage->URL;
        $item->mediumImage = $product->MediumImage->URL;
        $item->largeImage = $product->LargeImage->URL;

				$jsonData = array();
				$jsonData['Title'] = $arr['ItemAttributes']['Title'];

				if(isset($arr['ItemAttributes']['ListPrice'])) {
					$jsonData['ListPrice'] = $arr['ItemAttributes']['ListPrice']['FormattedPrice'];
					$jsonData['CurrencyCode'] = $arr['ItemAttributes']['ListPrice']['CurrencyCode'];
				}
				//EditorialReviews
				if(isset($arr['SmallImage'])) {
					$jsonData['SmallImage'] = $arr['SmallImage']['URL'];
					$jsonData['MediumImage'] = $arr['MediumImage']['URL'];
					$jsonData['LargeImage'] = $arr['LargeImage']['URL'];
				}
				$jsonData['ASIN'] = $arr['ASIN'];
				if(isset($arr['ParentASIN'])) {
					$jsonData['ParentASIN'] = $arr['ParentASIN'];
				}
				$jsonData['DetailPageURL'] = $arr['DetailPageURL'];
				if(isset($arr['SalesRank'])) {
					$jsonData['SalesRank'] = $arr['SalesRank'];
				}
				$jsonData['link'] = $item->link;
				if(isset($arr['OfferSummary']['LowestNewPrice'])) {
					$jsonData['LowestNewPrice'] = $arr['OfferSummary']['LowestNewPrice']['FormattedPrice'];
				}
				
				if(isset($arr['ItemAttributes']['ReleaseDate'])) {
					$jsonData['ReleaseDate'] = $arr['ItemAttributes']['ReleaseDate'];
				}
				if(isset($arr['EditorialReviews']['EditorialReview'])) {
					$EditorialReview = $arr['EditorialReviews']['EditorialReview'];
					foreach($EditorialReview as $review) {
						if(is_array($review)) {
							if(in_array("Product Description", $review)) {
								$jsonData['ProductDescription'] = htmlentities($review['Content']);
							}
							continue;
						} else {
							if(in_array("Product Description", $EditorialReview)) {
								$jsonData['ProductDescription'] = htmlentities($EditorialReview['Content']);
							}
						}
					}
				}
				/*
				p(is_array($EditorialReview));
				if(count($arr['EditorialReviews']) >1) {	
					foreach($EditorialReview as $review) {
						if(in_array("Product Description", $review)) {
							$jsonData['ProductDescription'] = $review['Content'];
						}
					}
				} else {
					if(in_array("Product Description", $arr['EditorialReviews']['EditorialReview'])) {
							$jsonData['ProductDescription'] = $arr['EditorialReviews']['EditorialReview']['Content'];
					}
				}
				*/
				//p($arr['EditorialReviews']['EditorialReview']['Content']);
				//MediumImage
//				$jsonData['CurrencyCode'] = $product->ItemAttributes->ListPrice->CurrencyCode;
				//p(json_encode($jsonData),$jsonData, $arr, $arr['OfferSummary']);


				//p(json_encode($jsonData));
			//	p($jsonData, $product->ItemAttributes->ListPrice, $product->OfferSummary->);
        $item->price = $this->get_item_price($product->OfferSummary);
				if($ret == "array") {
					return $jsonData;
				}
				return htmlspecialchars(json_encode($jsonData), ENT_QUOTES, 'UTF-8');
        return $item;
    }

}
/*
if(empty($_GET['ASIN'])) {
	die("please send ASIN in url");
}
*/
$amazon = new AmazonAPI("rashvin-20", "AKIAINK6LYGABIVDCLVQ", "3bzKyuQQpnCcoWfw/dBzne1Rng+YJ+Lh4VP/2yBe");
//$item = $amazon->item_lookup("B06XX29S9Q")->get_item_data(); //B004GVZUUY
//$item = $amazon->item_lookup("0439023521")->get_item_data(); p($item);
//$json_decode = htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8');
//p($json_decode);
//p(stripslashes(json_encode($item)));
//$item = $amazon->item_search("the hunger games")->get_items_data();

//$item = $amazon->item_lookup($_GET['ASIN'])->get_item_data();
//$item = $amazon->item_lookup("B00ZV9RDKK")->get_item_data();
//$item = $amazon->item_lookup("B004GVZUUY")->get_item_data();
//p("sdsd",$item);
//var_dump($item);
/*
echo $item->title;
echo $item->price;
echo "<img src='$item->mediumImage'>";
*/
function p() {
  $v = 0;
  $args = func_get_args();
  echo "<pre>";
  if (count($args)) {
    foreach ($args as $v) {
      var_dump($v);
      echo '<hr>';
    }
  }
  if ($v !== 1) {
    exit;
  }
}
function get_attributes($attr = array()) {
    $str = '';
    foreach ($attr as $k => $v) {
        $str.= $k . '="' . htmlentities($v) . '" ';
    }
        return $str;
}
function post_value($field, $html_safe = false) {
    return check_value($_POST, $field, $html_safe);
}
function check_value($arr, $field, $html_safe = false) {
    if (isset($arr[$field])) {
        if ($html_safe) {
            $return = html_safe($arr[$field]);
        } else {
            $return = $arr[$field];
        }
    } else {
        $return = '';
    }
    return $return;
}
function html_safe($str) {
    if (is_string($str)) {
        return htmlentities($str, ENT_QUOTES);
    } else {
        return $str;
    }
}
function generate_select($options = array(), $attr = array(), $default = '', $select = true) {
    if (!is_array($default)) {
        $default = array($default);
    }
          
    if (isset($attr['multiple'])) {
		         $attr['name'] = $attr['name'] . '[]';
    }
    $str = '<select ';
    $str.= get_attributes($attr);
    $str.= ' >';
    if ($select) {
        if (is_string($select)) {
            $str.= '<option value="" >' . $select . '</option> ';
        } else {
            $str.= '<option value="" >Select</option> ';
        }
    }
    foreach ($options as $k => $v) {
        $selected = (in_array($k, $default) ? ' selected = "selected" ' : '');
        $str.= '<option value="' . $k . '" ' . $selected . '>' . htmlentities($v) . '</option> ';
    }
    $str.= '</select>';
    return $str;
}
$searchfor = post_value('searchfor');
echo "
<br /><br /><br /><br /><br />
<form method='post' action=''>Select Search Option:"
	.generate_select(array('itemLookup'=> 'Item Lookup', 'itemSearch'=>'Item Search'), array('name' => 'serachType', 'required'=> 'required'), post_value('serachType'), 'Please Select').
	"<br />ASIN/Item Name<input type='text' name='searchfor' required value='$searchfor'><br />
	<input type='submit' value='Search'>
</form>";

if(isset($_POST) && isset($_POST['searchfor']) && isset($_POST['serachType'])) {
	$searchfor  =$_POST['searchfor'];
	$serachType  =$_POST['serachType'];
	if($serachType == 'itemLookup') {
		$item = $amazon->item_lookup($searchfor)->get_item_data(); 
		echo $item;
	}
	if($serachType == 'itemSearch') {
		$item = $amazon->item_search($searchfor)->get_items_data();
		//$item = $amazon->item_lookup($searchfor)->get_item_data(); 
		echo $item;
	}
}
//p($_POST);