<?php

require_once('player.php');

class Robot extends Player{
    
    
    protected $serverOnly = false;
    
    private $costWeights = array( 'military' => 1, 'points' => 1, 'coins'=>1, 'cards'=>1, 'wonder'=>1, 'cost'=>1, 'build'=>1 );
    public function __construct($id, $unique, $serverOnly = false, $costWeights = "random" ) {
        $this->_name = "$unique";
        $this->_id = $id;
        $this->serverOnly = $serverOnly;
        if ( $costWeights === false ){
            foreach ( $this->costWeights as $name => $weight ){
                $this->costWeights[$name] = rand ( 0 , 40 ) / 10;
            }
        } else if ( is_string($costWeights) ) {
            $players = json_decode(file_get_contents("cards/robots.json"), true);
            $player = null;
            foreach ( $players as $p ){
                if ( $p['name'] == $costWeights ){
                    $players = $p;
                }
            }            
            if ( !$player ) {
                $player = $players[ rand ( 0 , count($players)-1 ) ];
            }
            $this->costWeights = $player["weights"];
            $this->setName( $player["name"] );
        } else if (is_array( $costWeights ) ) {           
            $this->costWeights = $costWeights;
        }
    }
    public function clonePlayer() {
        $player = new Robot( "c".$this->_id, 0, $this->serverOnly, $this->costWeights );
        $player->setName( "c". $this->name() );
        return $player;
    }
    public function mutePlayer( $probs = 0.2 ) {
        $weights = $this->costWeights;
        foreach( $weights as $name=>$weight ){
            if ( rand ( 0 , 100 ) < $probs * 100 ){
                $weights[$name] = $weight + rand ( -10 , 10 ) / 10;
                if ( $weights[$name] < 0 ){
                    $weights[$name] = 0;
                }
            }
        }
        $player = new Robot( "m".$this->_id, 0, $this->serverOnly, $weights );
        $player->setName( "m". $this->name() );
        return $player;
    }
    public function matePlayer( Robot $otherPlayer, $probs = 0.5 ) {
        $weightsA = $this->costWeights;
        $weightsB = $otherPlayer->costWeights;
        foreach( $weightsA as $name=>$weight ){
            if ( rand ( 0 , 100 ) < $probs * 100 ){
                $weightsA[$name] = $weightsB[$name];                
            }
        }
        $player = new Robot( "x".$this->_id, 0, $this->serverOnly, $weightsA );
        $player->setName( "(".$otherPlayer->name() . "x" . $this->name().")" );
        return $player;
    }
    public function getCostWeights() {
        return $this->costWeights;
    }
    public function id() {
        return $this->_id;
    }

    public function name() {
        return $this->_name;
    }

    public function setName($name) {
        $this->_name = $name;
    }

    public function info() {
        return "User {$this->name()} ({$this->id()})";
    }
    public function isRobot() {
        return true;
    }
    public function send($type, $msg) {
        if ( $this->serverOnly ){
            return;
        }
        return parent::send($type, $msg);
    }
    public function getPublicInfo(){
        return array(
            'id' => $this->_id,
            'name' => $this->_name,
            'cards' => array_map(function($c) { return $c->json(); }, $this->cardsPlayed),
            'coins' => $this->coins,
            'wonder' => array("name" => $this->wonderName,
                              "stage" => $this->wonderStage,
                              "side" => $this->wonderSide),
            'military' => $this->military->json()
        );
    }

    public function setGame($game) {
        parent::setGame( $game );
		
    }
	
    public function sendHand() {
        // called once per turn, reset calculated card possibilities
        $this->possibilities = array();
        if(isset($this->hand)){
			
            $action = "";
			$max = 0;
            $index = 0;
			$best = "";
            $names = array();
			foreach ( $this->hand as $card ){
                $names[] = $card->getName();
                $type = 'play';
				$card->possibilities = $this->calculateCost($card, $type);
				// Save off what we just calculated so we can verify a cost strategy
				// when one is provided when playing the card
				$this->possibilities[$this->cardCostName($card, $type)] = $card->possibilities;				
				$card->value = $this->getCardValue($card, $type, $card->possibilities);			
				if ( $card->value['value'] > $max ){
					$max = $card->value['value'];
                    $index = $card->value['index'];
					$best = $card->getName();
                    $action = "buying";
				}                
			}
			$type = 'wonder';
			$wonderPossibilities = $this->calculateCost($card, $type);
			$this->possibilities[$this->cardCostName($card, $type)] = $wonderPossibilities;				
			$wonderValue = $this->getCardValue($card, $type, $wonderPossibilities);
			if ( $wonderValue['value'] > $max ){
				$max = $wonderValue['value'];
                $index = $wonderValue['index'];
				$action = "building";
			}
            if ( $max <= 1 ){
                $action = "trashing";
                $index = 0;
            }
            if ( $action !== "buying" ){
                $best = $names[rand(0, count($names)-1 )];
            }
            $info = array_map(function($c) { return $c->json(); }, $this->hand);
            if ( $this->serverOnly ){
                // we know wat teh client would choose, let's do so
                $actionMap = array( "trashing"=>"trash", "building"=>"wonder", "buying"=>"play");
                $args = array(
                    'messageType' => 'cardplay',
                    'value' => array( $best,  $actionMap[$action], $index  )
                );
                $this->game()->onMessage($this, $args);
            } else {
                $this->send('hand',
                           array('age' => $this->game()->age, 'cards' => $info, 'action' => $action, 'selected' => $best, 'wonder' => $wonderValue, 'index' => $index  ));
            }
        }
    }
    public function sendStartInfo($isRejoin = false) {
        if ( $this->serverOnly ){
            switch ( $this->wonderName ){
                case "babylon":
                   $isA = true;
                    break;
                case "halikarnassus":
                    $isA = false;
                    break;
                case "olympia":
                    $isA = false;
                    break;
                default:
                    $isA = rand(0, 1) == 0;
                    break;                
            }
            $args = array(
                    'messageType' => 'wonderside',
                    'value' => $isA
                );
            $this->game()->onMessage($this, $args);            
            return;
        }
        return parent::sendStartInfo( $isRejoin );
    }
    public function getCardValue(Card $card, $type, $possibilities){
		$pos = array();
		// what can we do with this card ?
		if ( count( $possibilities) == 0 ) {
			return array ('value' => -1, 'info' => null, 'index' => 0 );
		}
		if ( $type == 'play' ) {			
			$info = array();
            $values = array();
			$points = 0;
			// military point
			$info['military'] = $card->getMilitary($this, $this->game()->age);
            $values['military'] = $info['military'];
			$points += $info['military'] * $this->costWeights['military'];
			
			// victory points ?
			if ( $info['military'] != 0 ){
				$info['points'] = 0;
			} else {				
				$info['points'] = $card->points($this, true);
				$points += $info['points'] * $this->costWeights['points'];
			}
			// coins			
			$info['coins'] = $card->getCoins( $this );
            $values['coins'] = $info['coins']/3;
			$points += $info['coins']/3 * $this->costWeights['coins'];
			
			// resources
			$resources = $card->getResources();
			$info['resources'] = array();
			foreach ( $resources as $resource ){
				$info['resources'] = $resource->json( true );
			}			
			
			// card usefulness (other cards that can be purchased now)
			$info['cards'] = array();
			$cardsLeft = $this->game()->deck->getLeftCards($this->game()->age, $this->game()->players );
			$values['cards'] = 0;
			foreach( $cardsLeft as $newCard){
				$gain = $this->checkCardCost($card, $newCard );
				if ( $gain > 0 ){
					$info['cards'][] = array( 'name' => $newCard->getName(), 'gain' => $gain );
                    // the gain is shared in the 2 cards, and we have 1/NBPlayer change to get it
					$points += $gain/2/count($this->game()->players) * $this->costWeights['cards'];
                    $values['cards'] += $gain/2/count($this->game()->players);
				}
			} 
			
			// wonder usefulness
			$info['wonder'] = $this->checkWonderCost( $card );	
            $values['wonder'] = 0;
			foreach( $info['wonder'] as $wonder){
				if ( $wonder > 0 ){
					$points += $wonder * $this->costWeights['wonder'];
                    $values['wonder'] += $wonder; 
                }
			}
            $minCost = $this->minCostExt($possibilities);
            $points -= $minCost[0]/3 * $this->costWeights['cost'];
            $values['cost'] = $this->minCost($possibilities)/3;
            
            $pos['index'] = $minCost[1];
			$pos['value'] = $points;        
			$pos['info'] = $info;
            
		} else {
            if ( count($this->wonder['stages']) == 4) {
                $vals = [ 3, 5, 5, 7, 0 ];
                $targts = [ 1, 2, 2, 3, 4 ];
                $value = $vals[$this->wonderStage];
                $targetAge = $targts[$this->wonderStage];
            } else {
                if ( $this->wonderStage < 3 ){
                    $value = ($this->wonderStage+1)*2+1;
                } else {
                    $value = 0;
                }
                $targetAge = $this->wonderStage+1;
            }
            $cardLeft = count($this->hand) / 2;
            
            if ( $targetAge < $card->getAge() ){
                if ( $cardLeft > 1 ){
                    $pos['value'] = $value + ($value+2)/($cardLeft-1);
                } else {
                    $pos['value'] = $value + ($value+2);
                }
            } else if ( $targetAge == $card->getAge() ){                
                $pos['value'] = $value / $cardLeft;
            } else {
                $pos['value'] = $value / 6 ;
            } 
            $pos['index'] = 0;
            $pos['value'] *= $this->costWeights['build'];
			$pos['info'] = null;
        }
        $pos['possibilities'] = $possibilities;
		return $pos;
	}
    public function findCost(Card $card, $type) {
        $possibilities = $this->calculateCost($card, $type);

        // Save off what we just calculated so we can verify a cost strategy
        // when one is provided when playing the card
        $this->possibilities[$this->cardCostName($card, $type)] = $possibilities;		
        // Send off everything we just found
        $this->send('possibilities', $pos);
    }
    private function minCostExt( $possibilities ) {
        $min = 100;
        $index = 0;
        foreach ( $possibilities as $i => $possibility ){
            $cost = 0;
            if ( isset( $possibility['left'] )){
                $cost += $possibility['left'];
            }
            if ( isset( $possibility['right'] )){
                $cost += $possibility['right'];
            }
            if ( $cost < $min ){
                $min = $cost;
                $index = $i;
            }
        }
        return array( $min, $index );
    }
    private function minCost( $possibilities ) {
        return $this->minCostExt( $possibilities )[0];
    }
	private function checkWonderCost(Card $card) {
		$result = array( 0, 0, 0, 0 );
        $gain3 = array( 3/2+5/3+7/4, 5/2+7/3, 7/2 );
        $gain4 = array( 3/2+5/3+5/4+7/5, 5/2+5/3+7/4, 5/2+7/3, 7/2 );
		$maxMoney = 6; // maximum sacrifice 2 victory points
		for ( $i=$this->wonderStage; $i<count($this->wonder['stages']); $i++ ){
			$stage = $this->wonder['stages'][$i];
            $required = $stage['requirements'];
			if ( count($this->wonder['stages']) == 4 ){
                $gain = $gain4[$i];
            } else {
                $gain = $gain3[$i];
            }
                        
			$haveBefore = $this->getAvailableResource();
			$possibleBefore = Resource::satisfy($required, $haveBefore, $maxMoney );
            
			if ( count($possibleBefore) > 0 ){
				$result[$i] -=  $gain - $this->minCost( $possibleBefore )/3;
			}
			$discounts = $card->getDiscounts();
			$haveAfter = $this->getAvailableResource( $card->getResources(), $discounts['left'], $discounts['right']);
			$possibleAfter = Resource::satisfy($required, $haveAfter, $maxMoney ); 			
			if ( count($possibleAfter) > 0 ){                
				$result[$i] +=  $gain - $this->minCost( $possibleAfter )/3;
			} else if ( count( $card->getResources() ) > 0 ){
                // check if this card is actually needed by our wonder even if not sufficient to get it now
                $have = array();
                foreach ($this->permResources as $resource){
                    $have[] = ResourceOption::me($resource);
                }
                $superResource = new Resource(false, true);
                $superResource->setAll( 1 ) ;
                for ( $j=0; $j<4; $j++ ){
                    $have[] = ResourceOption::left($superResource, $gain);
                }
                $before = Resource::satisfy($required, $have, 1000 );
                if ( count($before) == 0 ){
                    print_r( array( 'superResource' => $superResource, 'have' => $have, 'required' => Resource::cumul($required) ) );
                    continue;
                }
                $result[$i] = $before[0]['left'];
                
                foreach ($card->getResources() as $resource){
                    $have[] = ResourceOption::me($resource);
                }
                $after = Resource::satisfy($required, $have, 1000 );
                if ( count($after) == 0 ){
                    print_r( array( 'have' => $have, 'required' => Resource::cumul($required) ) );
                    continue;
                }
                $result[$i] -= $after[0]['left'];                
            }
		}
		return $result;
	}
	private function checkCardCost(Card $card, Card $newCard) {
		
		$gain = 0;
		$maxMoney = 6; // maximum sacrifice 2 victory points
		// check for duplicates
        if ($card->getName() == $newCard->getName())
            return 0;
        
		foreach ($this->cardsPlayed as $cardPlayed)
			if ($cardPlayed->getName() == $newCard->getName())
				return 0;

		// check if it's already free
		foreach ($this->cardsPlayed as $cardPlayed)
			if ($newCard->hasPrereq($cardPlayed))
				return 0;
		
		$required = $newCard->getResourceCost();
		
		$haveBefore = $this->getAvailableResource();
		
		$possibleBefore = Resource::satisfy($required, $haveBefore, $maxMoney);         
		if ( count($possibleBefore) > 0 ){
			$cost = $this->minCost( $possibleBefore );
			if ( $cost == 0 ) {
				return 0; // this is a simplification as some card may have an increased gain thanks to the card.
			}
			$gain -=  $newCard->points($this, true) - $cost/3;
		}
		
		if ($newCard->hasPrereq($card)){
			$gain +=  $newCard->points($this, true, $card);
		} else {
			$discounts = $card->getDiscounts();
			$haveAfter = $this->getAvailableResource( $card->getResources(), $discounts['left'], $discounts['right']);
			$possibleAfter = Resource::satisfy($required, $haveAfter, $maxMoney); 
			if ( count($possibleAfter) > 0 ){                
				$gain +=  $newCard->points($this, true, $card) - $this->minCost( $possibleAfter )/3;
			}
		}        
		return $gain;
	}
	private function getAvailableResource( $additionalResources = array(), $leftDiscount = array(), $rightDiscount = array() ) {
		// Otherwise, we're going to have to pay for this card somehow
        $have = array();

        // We get all our resources for free
        foreach ($this->permResources as $resource)
            $have[] = ResourceOption::me($resource);
		
		foreach ($additionalResources as $resource)
            $have[] = ResourceOption::me($resource);
			
        // Add in all the left player's resources, factoring in discounts
        foreach ($this->leftPlayer->permResources as $resource) {
            if (!$resource->buyable())
                continue;
            $have[] = ResourceOption::left($resource,
                            $resource->discount($this->discounts['left'] + $leftDiscount));
        }
        // Add in all the right player's resources, factoring discounts
        foreach ($this->rightPlayer->permResources as $resource) {
            if (!$resource->buyable())
                continue;
            $have[] = ResourceOption::right($resource,
                            $resource->discount($this->discounts['right'] + $rightDiscount));
        }
		return $have;
	}
    private function calculateCost(Card $card, $type) {
        if ($type == 'play') {
            // check for duplicates
            foreach ($this->cardsPlayed as $cardPlayed)
                if ($cardPlayed->getName() == $card->getName())
                    return array();

            // check if it's a prerequisite for being free
            foreach ($this->cardsPlayed as $cardPlayed)
                if ($card->hasPrereq($cardPlayed))
                    return array(array());

            $required = $card->getResourceCost();
        } else { // $type == 'wonder'
            // Can't over-build the wonder
            if ($this->wonderStage >= count($this->wonder['stages']))
                return array();
            $stage = $this->wonder['stages'][$this->wonderStage];
            $required = $stage['requirements'];
        }

        $have = $this->getAvailableResource();

        // Figure out how we can pay neighbors to satisfy our requirements
        $possible = Resource::satisfy($required, $have,
                                      $this->coins - $card->getMoneyCost());
        return $possible;
    }
    public function calcCards(  ){
        $cardsCount = array(
            Card::BLUE => 0,
            Card::GREEN => 0,
            Card::RED => 0,
            Card::YELLOW => 0,
            Card::PURPLE => 0,
            Card::BROWN => 0,
            Card::GREY => 0
        );
        foreach ( $cardsCount as $name => $val ){
            if ( $name != Card::PURPLE ){
                $cardsCount[$name."_1"] = $val;
                $cardsCount[$name."_2"] = $val;
            }
            if ( $name != Card::BROWN && $name != Card::GREY ){
                $cardsCount[$name."_3"] = $val;                
            }
        }
        foreach($this->cardsPlayed as $card){
            $name = $card->getColor()._.$card->getAge();            
            $cardsCount[$name]++;            
            $cardsCount[$card->getColor()]++;
        }
        return $cardsCount;
    }
}
