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
    public $nbSimul = -1;
    public $nbPlayers = 3;
    public function broadcast( $type, $msg, $exclude=null ){      
        if ( $type === 'score' ){
            // end of the game            
        }
        
    }
    public function start( ){
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


// Start game
$server = new Simulation();
if ( isset( $argv[1]) ){
    $server->nbPlayers = intval($argv[1], 10);
} else {
    $server->nbPlayers = 3;
}

if ( isset( $argv[2]) ){
    $simul = intval($argv[2], 10);
} else {
    $simul = 10;
}
while ( $simul-- ){
    $server->start(  );
}

