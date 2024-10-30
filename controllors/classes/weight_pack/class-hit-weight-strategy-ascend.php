<?php
	/**

*/

	if(!class_exists('WeightPackAscend')){
		class WeightPackAscend extends WeightPackStrategy{
			public function __construct(){
				parent::__construct();
			}

			public function pack_items(){
				$items=$this->get_packable_items();
				usort($items,	array($this,	'sort_items'));
				$max_weight	=	$this->get_max_weight();
				if(is_numeric($max_weight)){
					$result	=	$this->pack_util->pack_items_into_weight_box($items,	$max_weight);
				}else{
					$result	=	$this->pack_util->pack_all_items_into_one_box($items);
				}	
				$this->set_result($result);
			}

			public function sort_items($a,	$b){
				$weight_a	=	(float) ($a['weight']);
				$weight_b	=	(float) ($b['weight']);
				if ($weight_a == $weight_b) {
					return 0;
				}
				return ($weight_a > $weight_b) ? +1 : -1;
			}
		}
	}