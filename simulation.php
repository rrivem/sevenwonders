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
    public $game;
    public $nbSimul = 2;
    public $nbPlayers = 3;
    public $dataFilename = "game_info.csv";
    

    public function broadcast( $type, $msg, $exclude=null ){     
        echo $type;
        if ( $type === 'scores' ){
            // end of the game  
            
            $scores = array();
            if ( !file_exists("stats/$this->dataFilename")  ){
                $str = "players,wonder_side,";
                $player = $this->game->players[0];
                $points = $player->calcPoints( true );
                $str .= implode(",", array_keys($points)) . ",";
                $weights = $player->getCostWeights();
                $str .= implode(",", array_keys($weights));
                $data[] = $str;
            } 

            $winners = array();
            foreach($this->game->players as $player){
                $points = $player->calcPoints( true );
                $scores[$player->id()] = $points;
                
                $weights = $player->getCostWeights( );
                $info = $player->getPublicInfo();                        
                $str = "";
                $str .= count( $this->game->players ).",";
                $str .= $info['wonder']["name"]."_".$info['wonder']["side"].",";
                $str .= implode(", ", $points) .",";
                $str .= implode(", ", $weights);                       

                $winners[$info['wonder']["name"]."_".$info['wonder']["side"]]=$points['total'];
                $data[] = $str;
                
            }
            asort( $winners );
            $str = "";
            foreach($winners as $name => $score ){            
                $str .= "," .$name;
            } 
            for ( $i=0; $i<count($data); $i++ ) {
                $data[$i] .= $str;
            }
            print_r($data);
            if ( count($data) ) {
                file_put_contents("stats/$this->dataFilename", implode("\n", $data) ."\n", FILE_APPEND);
            }
        }
        
    }
    public function start( ){
        while ( $this->nbSimul-- ){
            $this->startGame(  );
        }
    }
    public function startGame( ){
        $this->game = new SevenWonders();
        $this->game->maxplayers = $this->nbPlayers;
        $this->game->name = "simulation";
        $this->game->id = gentoken();
        $this->game->server = $this;
        
        for ( $r=0; $r<$this->game->maxplayers; $r++ ){
            $user = new Robot(gentoken(), $r, true);
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


