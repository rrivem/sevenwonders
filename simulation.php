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

// Run from command prompt > php demo.php
require_once("wonders.php");
require_once("player.php");
require_once("robot.php");

class Simulation {
    public $nbSimul = 1000;
    public $nbPlayers = 3;
    
    public $simulationType = "herd";
    public $herdSize = 100;
    
    public $dataFilename = "game_info";
    public $statsFilename = "game_stats";
    
    protected $game;
    protected $herd;
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
            asort( $winners );
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
        if ( $this->simulationType === "herd" ){
            for ( $i=0; $i<$this->herdSize; $i++){
                $this->herd[] = new Robot(gentoken(), $i, true);
            }
        }
        $idx = 0;
        while ( $this->nbSimul-- ){
            $this->startGame( $idx++ );
        }
        $filename = "stats/".$this->statsFilename . count( $this->game->players ) .".csv";
        file_put_contents($filename, $this->dumpStats( ));
    }
    public function startGame( $gameIdx ){
        $this->game = new SevenWonders();
        $this->game->debug = false;
        $this->game->maxplayers = $this->nbPlayers;
        $this->game->name = "simulation";
        $this->game->id = gentoken();
        $this->game->server = $this;
        if ( $this->simulationType === "herd" ){
            shuffle($this->herd);
        }
        for ( $r=0; $r<$this->game->maxplayers; $r++ ){
            if ( $this->simulationType === "random" ){
                $user = new Robot(gentoken(), $gameIdx."_".$r, true);
            } else  if ( $this->simulationType === "herd" ){                      
                $user = $this->herd[$r];
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


