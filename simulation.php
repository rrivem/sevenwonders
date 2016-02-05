#!/php -q
<?php

// Set date to avoid errors
date_default_timezone_set("America/New_York");

function gentoken() {
    $chars = "abcdefghijklmnopqrstuvwxyz1234567890";
    $string = "";
    $nchars = strlen($chars);
    for ($i = 0; $i < 30; $i++)
        $string .= $chars[mt_rand(0, $nchars - 1)];
    return $string;
}
function sortVictory($a, $b) {
    $totA = array_sum( $a->victories );
    $totB = array_sum( $b->victories );
    if ( $totA == 0) return -1;
    if ( $totB == 0) return 1;
    $ptA = $a->victories[0]*4+$a->victories[1]*2+$a->victories[3];
    $ptB = $b->victories[0]*4+$b->victories[1]*2+$b->victories[3];                
    return $ptA/$totA>$ptB/$totB?1:-1;
}
function sortPoints($a, $b) {
    $totA = array_sum( $a->victories );
    $totB = array_sum( $b->victories );
    if ( $totA == 0) return -1;
    if ( $totB == 0) return 1;
    $ptA = $a->totalScore;
    $ptB = $b->totalScore;                
    return $ptA/$totA>$ptB/$totB?1:-1;
}
function sortMedian($a, $b) {
    if ( count( $a->historicalScores) == 0 ) return -1;
    if ( count( $b->historicalScores) == 0  ) return 1;
    $ptA = $a->medianScore();
    $ptB = $b->medianScore();
    return $ptA>$ptB ? 1 : -1 ;
}
// Run from command prompt > php demo.php
require_once("wonders.php");
require_once("player.php");
require_once("robot.php");

class Simulation {
    public $nbSimul = 10000;
    public $nbPlayers = 3;
    
    // random, herd, group
    public $simulationType = "group";
    public $herdSize = 24;
    
    // for selection
    public $rounds = 240; // number of round before a selection is made
    public $herdChange = 6; // number of individuals that are changed in the herd
    public $newRobotType = ["mute", "mute" ]; // random, clone, mute, mate, new, array is uspported
    
    public $loadHerd = false;
    public $dataFilename = "game_info";
    public $statsFilename = "game_stats";
    public $herdFilename = "herd";
    
    public $sortFunc = "sortPoints";
    
    protected $game;
    protected $herd;
    protected $herdSelection = array();
    protected $wonderStats = array(
        'positions' => array(),
        'details' => array(),
        'score' => array( 'all' => array(  'all' => array() ), 'win'=>array( 'all' => array() ) ),
        'weights' => array(),
        'cards' => array( 'all' => array( 'all' => array() ), 'win'=>array( 'all' => array() )),
        'colors' => array( 'all' => array(  'all' => array() ), 'win'=>array( 'all' => array() ))
        
    );
    protected $playerStats= array(
        'positions' => array(),
        'details' => array(),
        'score' => array( 'all' => array(  'all' => array() ), 'win'=>array( 'all' => array() ) ),
        'weights' => array(),
        'cards' => array( 'all' => array( 'all' => array() ), 'win'=>array( 'all' => array() )),
        'colors' => array( 'all' => array(  'all' => array() ), 'win'=>array( 'all' => array() ))
    );
    protected function addHist( $src, &$dst ){
        foreach ( $src as $color => $point ){
            if ( !isset($dst[$color])){
                $dst[$color] = array();
            }
            if ( !isset($dst[$color][$point])){
                $dst[$color][$point] = 1;
            } else {
                $dst[$color][$point]++;
            }
        }
    }
    protected function addStats( &$where, $name, $score, $pos, $winner, $playerData ){        
        $player = $playerData[0];
        $points = $playerData[1];
        $cards = $player->calcCards();
        if ( !isset($where['positions'][$name])){
            $where['positions'][$name] = array_fill(0, 7, 0 );
        }
        if ( !isset($where['score']['win'][$name])){
            $where['score']['win'][$name] = array_fill(0, 30, 0 );
            $where['score']['win'][$name]['vals'] = array();
        }
        if ( !isset($where['score']['win']['all'])){
            $where['score']['win']['all'] = array_fill(0, 30, 0 );
            $where['score']['win']['all']['vals'] = array();
        }
        if ( !isset($where['score']['all'][$name])){
            $where['score']['all'][$name] = array_fill(0, 30, 0 );
            $where['score']['all'][$name]['vals'] = array();
        }
        if ( !isset($where['score']['all']['all'])){
            $where['score']['all']['all'] = array_fill(0, 30, 0 );
            $where['score']['all']['all']['vals'] = array();
        }
        if ( !isset($where['color'][$name])){
            $where['color'][$name] = array();
        }
        if ( !isset($where['cards'][$name])){
            $where['cards'][$name] = array();
        }
        
        $where['positions'][$name][$pos]++;
        $histscore = round( $score / 3 );
        if ( $pos == 0 ){            
            if ( !isset($where['details'][$winner])){
                $where['details'][$winner] = array( 'total' => 0 );
            }
            $where['details'][$winner]['total']++;
            
            $where['score']['win'][$name][$histscore]++;
            $where['score']['win'][$name]['vals'][] = $score;
            $where['score']['win']['all'][$histscore]++;
            $where['score']['win']['all']['vals'][] = $score;
            
            $this->addHist( $points, $where['colors']['win'][$name] );
            $this->addHist( $cards, $where['cards']['win'][$name] );          
            $this->addHist( $points, $where['colors']['win']['all'] );
            $this->addHist( $cards, $where['cards']['win']['all'] );          
        } else {                                
            if ( !isset($where['details'][$winner][$name])){
                $where['details'][$winner][$name] = 0;
            }
            $where['details'][$winner][$name]++;
        }                        
        $where['score']['all'][$name][$histscore]++;        
        $where['score']['all'][$name]['vals'][] = $score;        
        $where['score']['all']['all'][$histscore]++;        
        $where['score']['all']['all']['vals'][] = $score;
        
        $where['weights'][$name] = $player->getCostWeights();
        $this->addHist( $points, $where['colors']['all'][$name] );
        $this->addHist( $cards, $where['cards']['all'][$name] );
        $this->addHist( $points, $where['colors']['all']['all'] );
        $this->addHist( $cards, $where['cards']['all']['all'] );
        
    }
    public function broadcast( $type, $msg, $exclude=null ){     
        echo $type ."\n";
        if ( $type === 'scores' ){
            // end of the game  
            
            $scores = array();
            $filename = "stats/".$this->dataFilename . count( $this->game->players ) .".csv";
            if ( !file_exists($filename)  ){
                $str = "name,wonder_side,";
                $player = $this->game->players[0];
                $points = $player->calcPoints( true );
                $str .= implode(",", array_keys($points)) . ",";
                $weights = $player->getCostWeights();
                $str .= implode(",", array_keys($weights)). ",";                
                $str .= implode(",", array_keys($player->victories)). ",";
                $str .= "totalScore";
                $data[] = $str;
            } 

            $winners = array();
            $winnersPlayers = array();
            foreach($this->game->players as $idx => $player){
                $points = $player->calcPoints( );
                $scores[$player->id()] = $points;
                
                $weights = $player->getCostWeights( );
                $info = $player->getPublicInfo();                        
                $str = "";            
                $str .= $player->name().",";
                $str .= $info['wonder']["name"]."_".$info['wonder']["side"].",";
                $str .= implode(",", $points) .",";
                $str .= implode(",", $weights).",";              
                $str .= implode(",", $player->victories).","; 
                $str .= $player->totalScore; 
                $wn = $info['wonder']["name"]."_".$info['wonder']["side"];
                $winners[$wn]=$points['total'];                
                $winnersData[$wn] = array( $player, $points, $info);
                
                $winnersPlayers[$player->name()] = $points['total'];
                $winnersPlayersData[$player->name()] = array( $player, $points, $info);
                $data[] = $str;                           
            }
            arsort( $winners );            
            $str = "";
            $pos = 0;
            
            foreach($winners as $name => $score ){                  
                $str .= "," .$name;
                if ( $pos == 0 ){
                    $winner = $name;
                }                
                $this->addStats( $this->wonderStats, $name, $score, $pos, $winner, $winnersData[$name] );                
                $pos++;
            } 
            
            arsort( $winnersPlayers );
            $playerPos = 0;
            foreach($winnersPlayers as $name => $score ){                
                if ( $playerPos == 0 ){
                    $winner = $name;
                }
                $this->addStats( $this->playerStats, $name, $score, $playerPos, $winner, $winnersPlayersData[$name] );                
                $playerPos++;
            } 
            for ( $i=0; $i<count($data); $i++ ) {
                $data[$i] .= $str;
            }
            if ( count($data) ) {
                file_put_contents($filename, implode("\n", $data) ."\n", FILE_APPEND);
            }
            
            echo $this->dumpStats( false );
        }
        
    }
    protected function dumpPosition( $positions, $csv = true) {
        $stats = "";
        if ( $csv ){
            $stats .="name,win,2nd,3rd,4th,5th,6th,7th,tot_play\n";
        } else {
            $stats .="name            ,win,2nd,3rd,4th,5th,6th,7th,tot\n";
        }
        foreach($positions as $name => $scores ){
            $totalPlay = array_sum( $scores );
            if ( $csv ){
                $stats .= $name;
            }else {
                $stats .= sprintf("%-15s",$name);
            }
            foreach($scores as $score ){
                $stats .= sprintf(",%3d", round($score/$totalPlay*100));
            }
            $stats .= ",$totalPlay\n";
        }
        return $stats;
    }
    protected function dumpScore( $score ) {
        $stats = "";
        $stats .="name";
        $stats .=",average";
        $stats .=",median";
        for ($i=0; $i<30; $i++ ){
            $stats .=",".($i*3);
        }
        $stats .= "\n";  
        foreach($score as $name => $data ){
            if ( count($score[$name]['vals']) == 0 ){                
                continue;
            }
            $totScore = array_sum( $score[$name] );
            $average = array_sum( $score[$name]['vals'] ) / count($score[$name]['vals']);
            $median = sort( $score[$name]['vals'] )[floor(count($score[$name]['vals'])/2)];
            $stats .= $name;
            $stats .= ",".round($average*100)/100;
            $stats .= ",".$median;
            for ($i=0; $i<30; $i++ ){
                $stats .=",".round($score[$name][$i]/$totScore*100);
            }
            $stats .= "\n";  
        }        
        return $stats;
    }
    protected function dumpDetails( $details ) {
        $stats = "";
        
        $first = true;
        $total = 0;
        $names = array();
        foreach($details as $winner => $data ){
            $total += $data['total'];
            $names[] = $winner;
        }
        foreach($details as $winner => $data ){
            if ( $first ){
                $stats .="winner";
                foreach($names as $name ){
                    $stats .= ",".$name;                    
                }
                $stats .= ",total\n";
                $first = false;
            }
            $stats .= $winner;            
            foreach($names as $name ){
                if ( isset($details[$name][$winner]) && isset($details[$winner][$name])  ){
                    $stats .= ",".round($details[$winner][$name]/($details[$winner][$name] + $details[$name][$winner])*100);
                } else if ( isset($details[$winner][$name]) ) {
                    $stats .= ",100";
                } else if (isset($details[$name][$winner])) {
                    $stats .= ",0";
                } else {
                    $stats .= ", ";
                }
            }
            $stats .= ",".round( $details[$winner]['total']/$total*100 );
            $stats .= "\n";
        }        
        return $stats;
    }
    protected function dumpHist( $hist ) {
        $stats = "";
        $names = array();
        $colors = array();
        foreach($hist as $name => $data ){
            $names[] = $name;
            foreach($data as $color => $d ){
                if (isset( $colors[$color] )){
                    $index = $colors[$color];
                } else {
                    $index = array();
                }
                foreach( $d as $i => $v ){
                    if (!in_array($i, $index)){
                        $index[] = $i;
                    }
                }
                sort( $index );
                $colors[$color] = $index;
            }
        }
        ksort( $colors );
        foreach($colors as $color => $index ){
            $stats .= "\n$color\n"; 
            $stats .= "name"; 
            foreach ($index as $i ){
                $stats .= ",".$i; 
            }
            $stats .= "\n";
            foreach ($names as $name ){
                if ( !isset($hist[$name][$color]) ){
                    continue;
                }
                $stats .= "$name"; 
                $tot = array_sum( $hist[$name][$color] );
                foreach ($index as $i ){
                    $stats .= ",".round($hist[$name][$color][$i]/$tot*100); 
                }
                $stats .= "\n";
            }
        }
        return $stats;
    }
    public function dumpStats( $csv = true ){
        
        $stats = "";
        $stats .= "Position\n"; 
        $stats .= $this->dumpPosition ( $this->wonderStats['positions'], $csv );
        
        if ( $csv ){
            $stats .= "\nDetails\n"; 
            $stats .= $this->dumpDetails ( $this->wonderStats['details'] );
            
            $stats .= "\nScore\n"; 
            $stats .= $this->dumpScore ( $this->wonderStats['score']['all'] );
            
            $stats .= "\nWining score\n"; 
            $stats .= $this->dumpScore ( $this->wonderStats['score']['win'] );            
            
            $stats .= "\nScore by color\n";           
            $stats .= $this->dumpHist ( $this->wonderStats['colors']['all'] );            
            
            $stats .= "\nWining score by color\n";           
            $stats .= $this->dumpHist ( $this->wonderStats['colors']['win'] );            
            
            $stats .= "\nCards\n";           
            $stats .= $this->dumpHist ( $this->wonderStats['cards']['all'] );            
            
            $stats .= "\nWining cards\n";           
            $stats .= $this->dumpHist ( $this->wonderStats['cards']['win'] );            
        }
        
        if ( count( $this->herd ) >0 ){
            $stats .= "\n";    
            if ( $csv ){
                $stats .="name,win,2nd,3rd,4th,5th,6th,7th,score,".implode(",", array_keys($this->herd[0]->getCostWeights( ))).",tot\n";
            } else {
                $stats .="name           ,win,2nd,3rd,4th,5th,6th,7th,score,".implode(",", array_keys($this->herd[0]->getCostWeights( ))).",tot_play\n";
            }
        }
        uasort( $this->herd, $this->sortFunc );
        foreach($this->herd as $player ){
            $totalPlay = array_sum( $player->victories );
            if ( $totalPlay == 0 ){
                continue;
            }
            if ( $csv ){
                $stats .= $player->name();
            } else {
                $stats .= sprintf("%-15s",$player->name());
            }
            
            foreach($player->victories as $victory ){
                $stats .= sprintf(",%3d", round($victory/$totalPlay*100));
            }
            $stats .= sprintf(",%3d ",$player->totalScore/$totalPlay);
            $stats .= ",".implode(", ", $player->getCostWeights( ));
            $stats .= ",$totalPlay\n";
        }
        if ( $csv ){
            $stats .= "\nDetails\n"; 
            $stats .= $this->dumpDetails ( $this->playerStats['details'] );
            
            $stats .= "\nScore\n"; 
            $stats .= $this->dumpScore ( $this->playerStats['score']['all'] );
            
            $stats .= "\nWining score\n"; 
            $stats .= $this->dumpScore ( $this->playerStats['score']['win'] );            
            
            $stats .= "\nScore by color\n";           
            $stats .= $this->dumpHist ( $this->playerStats['colors']['all'] );            
            
            $stats .= "\nWining score by color\n";           
            $stats .= $this->dumpHist ( $this->playerStats['colors']['win'] );            
            
            $stats .= "\nCards\n";           
            $stats .= $this->dumpHist ( $this->playerStats['cards']['all'] );            
            
            $stats .= "\nWining cards\n";           
            $stats .= $this->dumpHist ( $this->playerStats['cards']['win'] );            
        }
        
        return $stats;
    }
    public function start( ){
        
        
        if ( $this->simulationType !== "random" ){
            if ( $this->loadHerd !== false  ){
                $herdFilename = "stats/".$this->loadHerd .".json";
                $herdData = json_decode(file_get_contents($herdFilename), true);  
                for ( $i=0; $i<$this->herdSize; $i++){
                    if ( isset( $herdData[$i]) ){
                        $robot = new Robot(gentoken(), $i, true, $herdData[$i]['weights'] );
                        if ( $this->simulationType === "herd" ){
                            $robot->setName( $herdData[$i]['name']);
                        } else {
                            $robot->setName( "-" . $herdData[$i]['name']);
                        }
                    } else {
                        if ( $i == $this->herdSize-1 ){
                            $robot = new Robot(gentoken(), $i, true, true);
                            $robot->setName("unit");
                        } else {
                            $robot = new Robot(gentoken(), $i, true, false);
                        }
                    }
                    $this->herd[] = $robot;
                }
            } else {
                for ( $i=0; $i<$this->herdSize-1; $i++){
                    $this->herd[] = new Robot(gentoken(), $i, true, false);
                }
                $unit = new Robot(gentoken(), $i, true, true);
                $unit->setName("unit");
                $this->herd[] = $unit;
            }
        }
        $idx = 0;
        while ( $this->nbSimul-- ){
            $this->startGame( $idx++ );
        }
        $filename = "stats/".$this->statsFilename . count( $this->game->players ) .".csv";
        file_put_contents($filename, $this->dumpStats( ));
        
        $herdData = array();
        foreach($this->herd as $player ){
            $herdData[] = array( 
                'name'=>$player->name(), 
                'weights' => $player->getCostWeights( ), 
                'victories' => $player->victories, 
                'totalScore'=> $player->totalScore
            );
        }   
        $herdFilename = "stats/".$this->herdFilename .".json";
        file_put_contents($herdFilename, json_encode( $herdData ));
        
    }
    public function startGame( $gameIdx ){
        $this->game = new SevenWonders();
        $this->game->debug = false;
        $this->game->maxplayers = $this->nbPlayers;
        $this->game->name = "simulation";
        $this->game->id = gentoken();
        $this->game->server = $this;
        
        if ( $this->herdChange > 0 ){
            if ( $gameIdx!=0 && $gameIdx % $this->rounds == 0 ){
                uasort( $this->herd, $this->sortFunc );                
                $this->herd = array_splice( $this->herd, $this->herdChange );
                
                $needUNit=1;
                foreach( $this->herd as $robot ){
                    if ( $robot->name() === 'unit'){
                        $needUNit = 0;                        
                    }
                }
                $top = count( $this->herd ) -1;
                if ( $needUNit ){
                    $unit = new Robot(gentoken(), 0, true, true);
                    $unit->setName("unit");
                    $this->herd[] = $unit;
                }                
                for ($i=$needUNit; $i< $this->herdChange; $i++ ){
                    if (is_array($this->newRobotType) ){
                        if ( isset($this->newRobotType[$i-$needUNit]) ){
                            $newRobotType = $this->newRobotType[$i-$needUNit];
                        } else {
                            $newRobotType = "random";
                        }
                    } else {
                        $newRobotType = $this->newRobotType;
                    }
                    if ( $newRobotType == "random" ){
                        $pos = ["new", "clone", "mute", "mate" ];
                        $newRobotType = $pos[rand(0,3)];
                    }
                    if ( $newRobotType == "clone" ) {
                        $this->herd[] = $this->herd[$top - $i  + $needUNit]->clonePlayer();
                    } else if ( $newRobotType == "mute" ) {
                        $this->herd[] = $this->herd[$top - $i  + $needUNit]->mutePlayer();
                    } else if ( $newRobotType == "mate" ) {
                        $this->herd[] = $this->herd[$top]->matePlayer( $this->herd[$top - $i  + $needUNit  -1 ] );
                    } else {
                        $this->herd[] = new Robot(gentoken(), $gameIdx.".".$i, true, false );
                    }
                }        
                
                $this->herdSelection = array();
            }
        }
        if ( $this->simulationType === "herd" ){
            if ( count( $this->herdSelection) < $this->game->maxplayers ){
                shuffle($this->herd);
                $this->herdSelection = $this->herd;
            }
        } else if ( $this->simulationType === "group" ){
            if ( !isset($this->herdSelection[0]) ){
                for ( $r=0; $r<$this->game->maxplayers; $r++ ){
                    $this->herdSelection[$r]= array();
                }
            }
            if ( count( $this->herdSelection[0]) == 0 ){
                uasort( $this->herd, $this->sortFunc );
                $idx = 0;                
                $max = round( count( $this->herd ) / $this->game->maxplayers );
                foreach( $this->herd as $robot ){                    
                    $this->herdSelection[$idx][] = $robot;
                    $max--;                    
                    if ( $max == 0 ){
                        $max = round( count( $this->herd ) / $this->game->maxplayers );
                        shuffle($this->herdSelection[$idx]);
                        $idx++;
                    }
                }
            }
        }
                
        for ( $r=0; $r<$this->game->maxplayers; $r++ ){
            if ( $this->simulationType === "random" ){
                $user = new Robot(gentoken(), $gameIdx."_".$r, true, false );
            } else  if ( $this->simulationType === "herd" ){                      
                $user = array_pop( $this->herdSelection );
            } else if ( $this->simulationType === "group" ){                
                $user = array_pop( $this->herdSelection[$r] );
            } 
            $this->game->addPlayer($user);
        }

    } 
}

if ( !file_exists("stats")){
    mkdir("stats");
}


// Start game
$server = new Simulation();

foreach ( $argv as $arg ){
    if ( substr($arg,0,1) == "-" ){
        $vals = explode("=" ,substr($arg,1), 2);
        $server->$vals[0] = $vals[1];
    }
}

$server->start();


