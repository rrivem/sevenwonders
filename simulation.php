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
// Run from command prompt > php demo.php
require_once("wonders.php");
require_once("player.php");
require_once("robot.php");

class Simulation {
    public $nbSimul = 900;
    public $nbPlayers = 4;
    
    // random, herd, select
    public $simulationType = "select";
    public $herdSize = 18;
    
    // for the selection type
    public $rounds = 45; // number of round before a selection is made
    public $herdChange = 6; // number of individuals that are changed in the herd
    
    public $loadHerd = false;
    public $dataFilename = "game_info";
    public $statsFilename = "game_stats";
    public $herdFilename = "herd";
    
    public $sortFunc = "sortPoints";
    
    protected $game;
    protected $herd;
    protected $herdSelection = array();
    protected $wonderStats;
    
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
            foreach($this->game->players as $player){
                $points = $player->calcPoints( true );
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
                $winners[$info['wonder']["name"]."_".$info['wonder']["side"]]=$points['total'];
                $data[] = $str;                           
            }
            arsort( $winners );
            $str = "";
            $pos = 0;
            foreach($winners as $name => $score ){            
                $str .= "," .$name;
                if ( !isset($this->wonderStats[$name])){
                    $this->wonderStats[$name] = array( 0, 0, 0, 0, 0, 0, 0);
                }
                $this->wonderStats[$name][$pos]++;
                $pos++;
            } 
            for ( $i=0; $i<count($data); $i++ ) {
                $data[$i] .= $str;
            }
            if ( count($data) ) {
                file_put_contents($filename, implode("\n", $data) ."\n", FILE_APPEND);
            }
            
            echo $this->dumpStats( );
        }
        
    }
    public function dumpStats( ){
        
        $stats = "";
        $stats .="name,           ,win,2nd,3rd,4th,5th,6th,7th,tot\n";
        foreach($this->wonderStats as $name => $scores ){
            $totalPlay = array_sum( $scores );
            $stats .= sprintf("%-15s",$name.",");
            foreach($scores as $score ){
                $stats .= sprintf(",%3d", round($score/$totalPlay*100));
            }
            $stats .= ",$totalPlay\n";
        }
        if ( count( $this->herd ) >0 ){
            $stats .= "\n";        
            $stats .="name,           ,win,2nd,3rd,4th,5th,6th,7th,score,".implode(",", array_keys($this->herd[0]->getCostWeights( ))).",tot\n";
        }
        uasort( $this->herd, $this->sortFunc );
        foreach($this->herd as $player ){
            $totalPlay = array_sum( $player->victories );
            if ( $totalPlay == 0 ){
                continue;
            }
            $stats .= sprintf("%-15s",$player->name().",");
            
            foreach($player->victories as $victory ){
                $stats .= sprintf(",%3d", round($victory/$totalPlay*100));
            }
            $stats .= sprintf(",%3d ",$player->totalScore/$totalPlay);
            $stats .= ",".implode(", ", $player->getCostWeights( ));
            $stats .= ",$totalPlay\n";
        }
        return $stats;
    }
    public function start( ){
        $herdFilename = "stats/".$this->herdFilename .".json";
        
        if ( $this->simulationType !== "random" ){
            if ( $this->loadHerd ){
                $herdData = json_decode(file_get_contents($herdFilename), true);  
                for ( $i=0; $i<$this->herdSize; $i++){
                    if ( isset( $herdData[$i]) ){
                        $robot = new Robot(gentoken(), $i, true, $herdData[$i]['weights'] );
                        if ( $this->simulationType === "herd" ){
                            $robot->setName( $herdData[$i]['name']);
                        } else {
                            $robot->setName( "x" . $herdData[$i]['name']);
                        }
                    } else {
                        if ( $i == $this->herdSize-1 ){
                            $robot = new Robot(gentoken(), $i, true, true);
                            $robot->setName("unit");
                        } else {
                            $robot = new Robot(gentoken(), $i, true);
                        }
                    }
                    $this->herd[] = $robot;
                }
            } else {
                for ( $i=0; $i<$this->herdSize-1; $i++){
                    $this->herd[] = new Robot(gentoken(), $i, true);
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
        file_put_contents($herdFilename, json_encode( $herdData ));
        
    }
    public function startGame( $gameIdx ){
        $this->game = new SevenWonders();
        $this->game->debug = false;
        $this->game->maxplayers = $this->nbPlayers;
        $this->game->name = "simulation";
        $this->game->id = gentoken();
        $this->game->server = $this;
        
        if ( $this->simulationType === "select" ){
            if ( $gameIdx!=0 && $gameIdx % $this->rounds == 0 ){
                uasort( $this->herd, $this->sortFunc );                
                $this->herd = array_splice( $this->herd, $this->herdChange );
                
                $needUNit=1;
                foreach( $this->herd as $robot ){
                    if ( $robot->name() === 'unit'){
                        $needUNit = 0;                        
                    }
                }
                if ( $needUNit ){
                    $unit = new Robot(gentoken(), $i, true, true);
                    $unit->setName("unit");
                    $this->herd[] = $unit;
                }
                for ($i=$needUNit; $i< $this->herdChange; $i++ ){
                    $this->herd[] = new Robot(gentoken(), $gameIdx.".".$i, true);
                }        
                
                $this->herdSelection = array();
            }
        }
        if ( $this->simulationType !== "random" ){
            if ( count( $this->herdSelection) < $this->game->maxplayers ){
                shuffle($this->herd);
                $this->herdSelection = $this->herd;
            }
        }
        for ( $r=0; $r<$this->game->maxplayers; $r++ ){
            if ( $this->simulationType === "random" ){
                $user = new Robot(gentoken(), $gameIdx."_".$r, true);
            } else  if ( $this->simulationType === "herd" || $this->simulationType === "select" ){                      
                $user = array_pop( $this->herdSelection );
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
$server->start();


