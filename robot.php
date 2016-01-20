<?php

require_once('player.php');

class Robot extends Player{
    
    protected $isRobot = false;
    
    private $costWeights = array( 'military' => 1, 'points' => 1, 'coins'=>1, 'cards'=>1, 'wonder'=>1, 'cost'=>1, 'build'=>1 );
    public function __construct($id, $unique) {
        $this->_name = "Robot $unique";
        $this->_id = $id;
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
		foreach ( $this->costWeights as $name => $weight ){
            $this->costWeights[$name] = rand ( 5 , 20 ) / 10;
        }
    }
	
    public function sendHand() {
        // called once per turn, reset calculated card possibilities
        $this->possibilities = array();
        if(isset($this->hand)){
			
            $action = "";
			$max = 0;
			$best = "discard";
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
					$best = $card->getName();
                    $action = "play";
				}                
			}
			$type = 'wonder';
			$wonderPossibilities = $this->calculateCost($card, $type);
			$this->possibilities[$this->cardCostName($card, $type)] = $wonderPossibilities;				
			$wonderValue = $this->getCardValue($card, $type, $wonderPossibilities);
			if ( $wonderValue['value'] > $max ){
				$max = $wonderValue['value'];
				$action = "building";
			}
            if ( $max <= 0.2 ){
                $action = "trashing";
            }
            if ( $action !== "play" ){
                $best = $names[rand(0, count($names)-1 )];
            }
            $info = array_map(function($c) { return $c->json(); }, $this->hand);
            $this->send('hand',
                       array('age' => $this->game()->age, 'cards' => $info, 'action' => $action, 'selected' => $best, 'wonder' => $wonderValue  ));
        }
    }

    public function getCardValue(Card $card, $type, $possibilities){
		$pos = array();
		// what can we do with this card ?
		if ( count( $possibilities) == 0 ) {
			return array ('value' => -1, 'info' => null );
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
            $points -= $this->minCost($possibilities)/3 * $this->costWeights['cost'];
            $values['cost'] = $this->minCost($possibilities)/3;
            
			$pos['value'] = $points;
            
			$pos['info'] = $info;
            
		} else {
            $value = ($this->wonderStage+1)*2+1;
            $cardLeft = count($this->hand) / 2;
            
            if ( $this->wonderStage < $card->getAge() - 1 ){
                if ( $cardLeft > 1 ){
                    $pos['value'] = $value + ($value+2)/($cardLeft-1);
                } else {
                    $pos['value'] = $value + ($value+2);
                }
            } else if ( $this->wonderStage == $card->getAge() - 1 ){                
                $pos['value'] = $value / $cardLeft;
            } else {
                $pos['value'] = $value / 6 ;
            } 
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
    private function minCost( $possibilities ) {
        $min = 100;
        foreach ( $possibilities as $possibility ){
            $cost = 0;
            if ( isset( $possibility['left'] )){
                $cost += $possibility['left'];
            }
            if ( isset( $possibility['right'] )){
                $cost += $possibility['right'];
            }
            if ( $cost < $min ){
                $min = $cost;
            }
        }
        return $min;
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
    
}
