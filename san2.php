<?php

class AmazonAPI {

    var $amazon_aff_id;
    var $amazon_access_key;
    var $amazon_secret_key;

    var $url_params;
    var $itemID;
    var $xml;

    var $operation;
    var $signature;
    var $response_groups = "Small,Images,OfferSummary";

    var $error_message;
    var $error=0;
		var $Keywords;
    

    public function __construct($affid, $access, $secret)
    {
        $this->amazon_aff_id = $affid;
        $this->amazon_access_key = $access;
        $this->amazon_secret_key = $secret;
    }

    public function build_url()
    {
        $url = "http://webservices.amazon.com/onca/xml?";

        //$this->response_groups = str_replace(",", "%2C", $this->response_groups);

        $url_params = "AWSAccessKeyId=" . $this->amazon_access_key;
        $url_params .= "&AssociateTag=" . $this->amazon_aff_id;

        if(!empty($this->itemID)) {
					  $url_params .= "&Keywords=" .  str_replace(" ", "%20","Potter");
						//p($url_params,1);
            //$url_params .= "&Keywords=" . "the%20hunger%20games";
						//p($url_params);
        }

        if(!empty($this->keywords)) {
            $url_params .= "&keywords=" . str_replace(",", "%2C", $this->keywords);
        }

        $url_params .= "&Operation=" . $this->operation ."&SearchIndex=Books";
        //$url_params .= "&ResponseGroup=" . $this->response_groups;
        $url_params .= "&Sort=titlerank&Service=AWSECommerceService";
        $url_params .= "&ItemPage=4";
        $url_params .= "&Timestamp=" . rawurlencode(gmdate("Y-m-d\TH:i:s\Z"));
        $url_params .= "&Version=2013-08-01";

        $this->url_params = $url_params;

        $url .= $url_params;
        $url .= "&Signature=" . $this->generate_signature();
p($url);
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
        $this->operation = "ItemSearch";
        $this->itemID = $id;

        $url = $this->build_url();
        $ch = curl_init();  

        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);

        $output = curl_exec($ch);

        curl_close($ch);

        $this->xml = simplexml_load_string($output);
        return $this;
    }

    public function item_lookups($id)
    {
        $this->operation = "ItemSearch";
        $this->Keywords = $id;

        $url = $this->build_url();
        $ch = curl_init();  

        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);

        $output = curl_exec($ch);

        curl_close($ch);

        $this->xml = simplexml_load_string($output);
				p($this->xml);
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

    public function get_item_data()
    {
        if($this->check_for_errors()) return null;
p($this->xml);
        $product = $this->xml->Items->Item;
				$json = json_encode($this->xml);
				p(json_decode($json));
        $item = new STDclass;
        $item->detailedPageURL = $product->DetailPageURL;
        $item->link = "https://www.amazon.com/gp/product/".$this->itemID."/?tag=" . $this->amazon_aff_id;
        $item->title = $product->ItemAttributes->Title;
        $item->smallImage = $product->SmallImage->URL;
        $item->mediumImage = $product->MediumImage->URL;
        $item->largeImage = $product->LargeImage->URL;

        $item->price = $this->get_item_price($product->OfferSummary);

        return $item;
    }

}


$amazon = new AmazonAPI("autoinsuquot-20", "AKIAINK6LYGABIVDCLVQ", "3bzKyuQQpnCcoWfw/dBzne1Rng+YJ+Lh4VP/2yBe");
$item = $amazon->item_lookup("B0016CBMZI")->get_item_data();
//p($item);
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
exit();
?>
