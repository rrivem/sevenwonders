This is a web port of the board game Seven Wonders. I do not own Seven Wonders
or its trademark, nor do I own any of the game's assets. Seven Wonders Online is
a purely educational endeavour to have fun while programming.

# Running the code

If you have php 5.4 installed, all you need to do is:

```
php -S localhost:9000
php server.php
```

And then visit `localhost:9000` in a browser. If you're using apache, then you
don't need the `php -S` command, just be sure to point apache at the root of the
repository.

## Robot web players

When starting the game using `localhost:9000` you can choose the number of robot players. 
If the number of robots is larger that the number of player in the game, robots in excess will be ignored. 

It is also possible to create robot web players (nice to see how the robot is actually playing).
To use robot players, use the following options:
* `robot=1` Create a robot (automatic player), the open page will play on its owns
* `autostart=1` Automatically starts a new game
* `players=X` When autostart is set, will create a game for X players. Default value is 3.

Example `http://localhost:9000/?robot=1&autostart=1`

When robot player are playing, a debugging number is shown under each cards. 
This is an estimated value for the card. 
The value of the card is computed using
* Direct point gains
* Possible military gains
* Coins increases
* Cards made accessible thanks to this card and increased of value of other cards not yet played thanks to this card
* Usefulness to build the wonder
* Cost of the cards

A random weight is used to make sure the robot is less predicable.

For the moment the robot is an average player, and quite easy to win against in 3-4 players games. 

## TODO (robot)
* Teach robot how to play halikarnassus
* Teach robot how to play babylon B
* Train and improve the robot
  * Find better rule for random weights
  * Do not use the same rule for all wonders
  * Take into account cards needed by the other players
  * Improve choice of discard cards or cards used to build the wonder (random so far)
  * Try other type of robot (neural networks for the weights for instance)

## Fixes needed
* Fix babylon B age 2 wonder if played on the last card of the age.