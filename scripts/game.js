var SevenWonders = function(socket, args){
    this.wonder = args.wonder;
    this.players = args.plinfo;
    this.coins = parseInt(args.coins);
    this.socket = socket;
    this.cardsPlayed = [];
    this.trashCardsDisplayed = [];
    this.hoverlock = 0;
    this.currentHoverCard = 0;
    this.leftPlayed = {};
    this.rightPlayed = {};
    this.neighbors = args.neighbors;
    this.wonderStage = 1;
    this.colorOrder = ['brown', 'grey', 'yellow', 'red', 'green', 'purple', 'blue'];
    this.cardWidth = 123;
    this.cardHeight = 190;
    this.hasfree = false; // has a free card from olympia's wonder
    this.scale = 1;
    this.gameDiv = $('#game');

	this.robot = args.robot;
    var self = this;
    
    // size game div acccording to browser size
    $("#game").css({width: Math.max($(window).width(), 1420), height: Math.max($(window).height(), 730)});

    $(window).resize(function(){
        if($(this).width() < 1420){
            self.scale = $(this).width() / 1420;
            $('#game').css({
                '-webkit-transform': 'scale(' + self.scale + ')',
                'margin-left': -1 * (1 - self.scale) * $('#game').width() / 2
            });
        } else {
            $('#game').css({
                '-webkit-transform': '',
                'margin-left': 0
            })
        }
    });
    $(window).resize();

    $('.neighbor.left > h1').html(this.neighbors.left.name)
    $('.neighbor.right > h1').html(this.neighbors.right.name)

    // Add boxes at the top showing name/wonder of non-adjacent players (if there are any)
    var boxes = [];
    for(var i = 0; i < this.players.length; i++){
        var player = this.players[i];
        if(player.id == localStorage.sevenwonders_id ||
           player.id == this.neighbors.left.id ||
           player.id == this.neighbors.right.id) continue;
        boxes.push(player);
    }

    for(var i = 0; i < boxes.length; i++){
        var player = boxes[i];
        var info = $('<div class="info">' + player.name + '</div>');
        var middle = this.gameDiv.width() / 2;
        info.addClass(player.wonder.name)
        info.css({
            left: middle - 85 + (boxes.length / 2 - i - 0.5) * 200,
            'background-image': 'url(images/wonders/' + player.wonder.name + player.wonder.side + '.png)'
        });
        info.data('player', player);
        this.gameDiv.append(info);
    }
   
    // select wonder image here (load in appropriately)
    if(typeof args.wonder.resource == 'undefined'){ // hacky way of checking if player refreshed in middle of wonder picking
        if ( !self.robot ) {
            $('#setup-container').fadeIn(1000);
            $('#setup p strong').html(this.wonder.name.capitalize());
            var imgname = "images/wonders/" + this.wonder.name.toLowerCase();
            $('#setup').append('<img src="' + imgname + 'A.png" /><img src="' + imgname + 'B.png" />')
            $('#setup img').click(function(){
                var isA = $(this).attr('src').indexOf('A') > -1;
                self.wonder = new WonderBoard({
                    name: args.wonder.name,
                    coins: parseInt(args.coins),
                    military: args.military,
                    wonderside: isA ? 'A' : 'B',
                    stage: 0,
                    game: self
                });
                $('#game').append(self.wonder.wonderDiv);

                self.send(isA, 'wonderside');
                $('#setup img').unbind('click');
                $('#setup-container').fadeOut(200, function(){
                    $(this).css('display', 'none');
                    // show waiting screen for until hand pops up
                })
            });
        } else {
            var isA = 1;
            switch ( this.wonder.name ){
                case "babylon":
                    isA = 1;
                    break;
                case "halikarnassus":
                    isA = 1;
                    break;
                case "olympia":
                    isA = 0;
                    break;
                default:
                    isA = Math.random() > 0.5;
                    break;                
            }
            self.wonder = new WonderBoard({
                name: args.wonder.name,
                coins: parseInt(args.coins),
                military: args.military,
                wonderside: isA ? 'A' : 'B',
                stage: 0,
                game: self
            });
            $('#game').append(self.wonder.wonderDiv);

            self.send(isA, 'wonderside');
            $('#setup-container').fadeOut(200, function(){
                $(this).css('display', 'none');
                // show waiting screen for until hand pops up
            })
        }
    }

    // Puts cards back in their place if player is rejoining the game (e.g. refreshing)
    if(args.rejoin == true){
        this.wonder = new WonderBoard({
            name: args.wonder.name,
            coins: parseInt(args.coins),
            military: args.military,
            wonderside: args.wonderside || 'A',
            stage: args.wonder.stage,
            game: this
        });
        $('#game').append(this.wonder.wonderDiv);

        this.showWonderResources();

        var i;
        for (i = 0; i < args.leftcards.length; i++)
            this.updateColumn('left', args.leftcards[i].color,
                              this.cardImageFromName(args.leftcards[i].name), 0);
        for (i = 0; i < args.rightcards.length; i++)
            this.updateColumn('right', args.rightcards[i].color,
                              this.cardImageFromName(args.rightcards[i].name), 0);
        for (i = 0; i < args.played.length; i++) {
            var div = this.cardDiv(0, args.played[i]);
            $('#game').append(div);
            this.wonder.moveToBoard(div, false);
       }
   }

   $('.neighbor').click(function(){
        var dir = $(this).hasClass('left') ? 'left' : 'right';
        var id = self.neighbors[dir].id;
        self.showPlayerInfo(id);
   });

   $('.info').click(function(){
        var player = $(this).data('player');
        console.log(player);
        self.showPlayerInfo(player.id);
   })
}

SevenWonders.prototype = {

    // Send a packet of information to the server via the WebSocket
    send: function(opts, type){
        opts = (typeof opts == "object" && !(opts instanceof Array)) ? opts : {value: opts};
        opts.messageType = type;
        this.socket.send(JSON.stringify(opts));
    },

    // Creates an img element from a full name of a card
    cardImageFromName: function(name){
        return '<img src="images/cards/' + name.toLowerCase().replace(/ /g, "") + '.png" />';
    },

    // Opens the discard or player card window
    showCardSelect: function(args, isDiscard){
        var cards = args.cards;
        var self = this;
        //set up card select window
        $("#cardselect").css({width: $('#game').width()-100, height: $('#game').height()-100});
        $('#cardselect').fadeIn(400);
        $('#cardwindow, #hovercardwindow').delay(200).fadeIn(300);
        $('#cardwindow > h1').html(isDiscard ? 'DISCARD PILE' : args.name); // if isDiscard

        //initialize variables and dummy data
        var count = cards.length+200;
        var selectCardWidth = 123;
        var selectCardHeight = 190;
        var self = this;

        //loop over all discarded cards, creating JQuery objects and Divs
        for(i in cards){
            var card = cards[i];
            var carddiv = this.cardDiv(count, card);
            carddiv.addClass('played ignore');
            $('#cardwindow').prepend(carddiv);

            carddiv.data('cardInfo', card);
            var cardColor = carddiv.data('cardInfo').color;
            var index = this.colorOrder.indexOf(cardColor);

            var numInColor = 0;
            for(i in this.trashCardsDisplayed)
                if(this.trashCardsDisplayed[i].data('cardInfo').color == cardColor) numInColor++; 

            carddiv.find('.options, h1').css('display', 'none');
			if ( isDiscard ) {
				var left = 10 + index * 136;
				var bottom = 10 + numInColor * 40;
			} else {
				var left = 168 + index * 136;
				var bottom = (cardColor == 'blue') ? 10 : 95 + numInColor * 40;
			}
            //var left = isDiscard ? 10 : 168 + index * 136;
            //var bottom = (isDiscard || cardColor == 'blue') ? 10 : 95 + numInColor * 40;
            carddiv.css({
                'z-index': 2000 - numInColor,
                'left': left,
                'bottom': bottom,
                'width': selectCardWidth,
                'height': selectCardHeight});

            //for each card set up hover and click actions
            carddiv.hover(
                function(){
                    if(!self.hoverlock || self.hoverlock == 2){
                        hoverCardDiv.find('img').attr('src',$(this).find('img').attr('src'));
                        hoverCardDiv.addClass('hovercard');
                        hoverCardDiv.find('h1')[0].innerHTML = $(this).find('h1')[0].innerHTML;
                        hoverCardDiv.data('cardInfo', $(this).data('cardInfo'));
                    }
                    if(!self.hoverlock)
                        $(this).find('img').css('box-shadow', '0px -3px 20px');
                },
                function(){
                    if(!self.hoverlock)
                        $(this).find('img').css('box-shadow', 'none');
                }
            );

            carddiv.click(function(e){
                e.stopPropagation();
                $(this).find('img').css('box-shadow', '0px -3px 20px');
                if(self.hoverlock){
                    if(this == self.currentHoverCard){
                        self.hoverlock = 0;
                        $(this).find('img').css('box-shadow', 'none');
                    }
                    else{
                        $(self.currentHoverCard).find('img').css('box-shadow', 'none');
                        self.hoverlock = 2;
                        $(this).trigger('mouseenter');
                        self.hoverlock = 1;
                    }
                }
                else
                    self.hoverlock = 1;
                self.currentHoverCard = this;
            });

            this.trashCardsDisplayed.push(carddiv);
            count--;
        }

        $('#cardwindow .wonder').remove();
        if(!isDiscard){
            var wonder = new WonderBoard({
                name: args.wonder.name,
                coins: args.coins,
                stage: args.wonder.stage,
                wonderside: args.wonder.side,
                military: args.military
            });
            $('#cardwindow').append(wonder.wonderDiv);
            this.trashCardsDisplayed.push(wonder.wonderDiv);
        }

        //allow for a click anywhere to deselect cards
        $('#cardwindow').click(function(e){
            if(isDiscard) e.stopPropagation();
            self.hoverlock = 0
            $(self.currentHoverCard).find('img').css('box-shadow', 'none');
        })

        $('#cardselect').click(function(){
            self.hideCardSelect();
        })

        //set up initial hover card div
        if(cards.length > 0){
            var hoverCardDiv = this.cardDiv(199, cards[0]);
            $('#hovercardwindow').prepend(hoverCardDiv);
            hoverCardDiv.data('cardInfo', cards[0]);
            hoverCardDiv.addClass('hovercard played ignore');
            hoverCardDiv.find('.options, h1').css('display', isDiscard ? 'block' : 'none');
            this.trashCardsDisplayed.push(hoverCardDiv);

            // For some reason, just setting the click function here DOESN'T F'ING WORK
            // As in, the click is never called. So the solution: I have to reset the click
            // later on in the process of loading the div. If a solution is found, please fix.
            setTimeout(function(){
                //bind send function to play button
                hoverCardDiv.find('.play').unbind('click').click(function(e){
                    e.stopPropagation();
                    var card = $(this).closest('.card');
                    var opts = {value: [card.find('h1').html(), 'play', 0]};
                    self.send(opts, 'cardplay');
                    var newCard = self.cardDiv(200, card.data('cardInfo'));
                    var offset = card.offset();
                    newCard.css({
                        bottom: $('#game').height() - offset.top,
                        left: offset.left
                    });
                    $('#game').append(newCard);
                    self.wonder.moveToBoard(newCard, true);
                    self.hideCardSelect();
                    return false;
                });
            }, 100);
        }
    },

    hideCardSelect: function(){
        //loop through the array of displayed cards and remove all of them
        var trash = this.trashCardsDisplayed;
        $('#cardwindow').delay(200).fadeOut(300);
        var self = this;
        $('#cardselect').fadeOut(400, function(){
            for(i in trash){
                trash[i].remove();
            }
        });
        this.trashCardsDisplayed = [];
    },


    // Inserts cards into the appropriate position on the left or right columns
    updateColumn: function(side, color, img, speed) {
        img = $('<div class="card ignore played">' + img + '</div>');
        var cardsPlayed = side == 'left' ? this.leftPlayed : this.rightPlayed;
        if(cardsPlayed[color] == undefined) cardsPlayed[color] = [];
        var length = cardsPlayed[color].length;
        img.appendTo('.neighbor.' + side);
        var bottom = -160;
        var lastIndex = 0;
        // Find the position relative to current # of cards
        for(var i = this.colorOrder.indexOf(color); i >= 0; i--){
            var col = this.colorOrder[i];
            lastIndex = i;
            if(cardsPlayed[col] && cardsPlayed[col].length > 0){
                var topCard = cardsPlayed[col][cardsPlayed[col].length - 1];
                bottom = img.get(0) == topCard ? 0 : parseInt($(topCard).css('bottom')) + 40;
                break;
            }
        }
        // Push up all cards above the one we're inserting
        for(var j = lastIndex + 1; j < this.colorOrder.length; j++){
            for(cIndex in cardsPlayed[this.colorOrder[j]]){
                var card_move = $(cardsPlayed[this.colorOrder[j]][cIndex]);
                card_move.animate({bottom: '+=40px'}, speed);
            }
        }
        img.css('bottom', bottom);
        img.css('z-index', 1000 * (8 - this.colorOrder.indexOf(color)) - length);
        cardsPlayed[color].push(img.get(0));
        img.animate({opacity: 1}, speed);
    },

    // Create a card div with options and slider
    cardDiv: function(idx, card) {
        var div = $('<div class="card" id="card' + idx +'">'+
                     '<h1>' + card.name + '</h1>' +
                     this.cardImageFromName(card.name) +
                     '<div class="options">\
                         <a href="#" class="trash"></a>\
                         <a href="#" class="play"></a>\
                         <a href="#" class="wonder"></a>\
                     </div>\
                     <div class="slider"><h2>Play Cost</h2>\
                            <div class="left"></div>\
                            <input type="range" min="0" value="0"/>\
                            <div class="right"></div>\
                    </div>\
                 </div>');
        div.data('cardInfo', card);
        return div;
    },

    // Highlight = when a card has been chosen for play
    // resetHighlight = user canceled that action, so ignore it
    resetHighlight: function(){
        var hand = $('.card:not(.played)');
        
        var old = $('.highlighted');
        if (old.length > 0) {
            this.send({card: old.find('h1').text()}, 'cardignore')
            old.removeClass('highlighted');
            old.find('.options a').css('visibility', 'visible')
                                  .animate({opacity: 1}, 200);
            old.find('.play').removeClass('buy no');
            old.find('.wonder').removeClass('free');
            this.resetCard(old);
        }
    },

    // Cards are modified in a number ways and used rather loosely
    // so resetCard puts a card back in its natural state like it was 
    // just dealt.
    resetCard: function(card) {
        var self = this;
        card.data('state', '');
        card.find('.trash').unbind('click').click(function(e){
            card.data('state', 'trashing');
            self.chooseCard(card, 0);
            return false;
        });

        card.find('.play').unbind('click').click(function(e){
            var opts = {value: card.find('h1').html(), type: 'play'};
            card.data('state', 'playing');
            self.send(opts, 'checkresources');
            self.resetHighlight();
            return false;
        });

        card.find('.wonder').unbind('click').click(function(e) {
            var opts = {value: card.find('h1').html(), type: 'wonder'};
            card.data('state', 'building');
            self.send(opts, 'checkresources');
            self.resetHighlight();
            return false;
        });

        card.find('.options a').css('visibility', 'visible')
            .animate({opacity: 1}, 200);
    },

    // Marks a card as set to be played when other players finish playing
    chooseCard: function(card, index) {
        this.resetHighlight();
        card.addClass('highlighted');
        var type = 'play';
        if (card.data('state') == 'trashing')
            type = 'trash';
        else if (index == -1)
            type = 'free';
        else if (card.data('state') == 'building')
            type = 'wonder';
        this.send([card.find('h1').html(), type, index], 'cardplay');

        var self = this;
        card.find('.options a').animate({ opacity: 0 }, 200, function(){
            $(this).removeClass('free buy');
            if ($(this).hasClass('play')) {
                $(this).addClass('no').animate({opacity: 1}, 200);
            } else {
                $(this).css('visibility', 'hidden');
            }
        });
        card.find('a.play')
            .unbind('click')
            .click(function() {
                $(this).animate({opacity: 0}, 200, function(){
                    self.resetHighlight();
                    $(this).removeClass('no');
                    self.resetCard(card);
                });
                return false;
            });
    },

    // Turns an enlarged card back to normal size
    shrinkCard: function(card){
        card = $(card);
        card.css('z-index', 1);
        card.animate({
            width: this.cardWidth,
            height: this.cardHeight,
            left: '+=25px',
            bottom: '+=38px',
            rotate: card.data('rotation')
        }, 200);
        card.removeClass('selected');
        card.find('.options, .slider').css('display', 'none');
    },

    showWonderResources: function(){
        $('.neighbor.left .info img.resource').attr('src', 'images/tokens/' + this.neighbors.left.resource + '.png');
        $('.neighbor.right .info img.resource').attr('src', 'images/tokens/' + this.neighbors.right.resource + '.png');
        $('.neighbor.left .info div.stage').css('background-image', 'url(images/tokens/pyramid-stage' + this.neighbors.left.stage + '.png)');
        $('.neighbor.right .info div.stage').css('background-image', 'url(images/tokens/pyramid-stage' + this.neighbors.right.stage + '.png)');

        $('.neighbor.left .info').css('background-image', 'url(images/wonders/' + this.neighbors.left.wonder + this.neighbors.left.wonderside  + '.png)');
        $('.neighbor.left .info').addClass(this.neighbors.left.wonder);
        $('.neighbor.right .info').css('background-image', 'url(images/wonders/' + this.neighbors.right.wonder + this.neighbors.right.wonderside + '.png)');
        $('.neighbor.right .info').addClass(this.neighbors.right.wonder);
    },

    cleanHand: function(){
        // Ignore all remaining cards on the board
        $('.card').addClass('ignore');
        $('.card:not(.highlighted, .played)').fadeOut(500, function(){
            $(this).remove();
        });

        // Use self to refer to SevenWonder object in callbacks/other scopes
        var self = this;
        // move selected card to board for later reference
        var selected = $('.card.highlighted');
        if(selected.length){
            $.each(selected, function(_, card) {
                self.wonder.moveToBoard($(card), true);
            });
        }
    },

    showPlayerInfo: function(id){
        this.send(id, 'playerinfo');
    },
    showSelectCard: function ( $target ) {
        // Lower opacity on non-selected cards
        $('.card:not(.ignore, #' + $target.attr('id') + ')').animate({ opacity: 0.1 }, 200);
        $target.addClass('selected');
        $target.animate({
            width: self.cardWidth + 50,
            height: self.cardHeight + 76.5,
            left: '-=25px',
            bottom: '-=38px',
            opacity: 1,
            rotate: 0
        }, 200, function(){
            $target.find('.options').fadeIn(200);
            $target.css('z-index', 2);

            // Show the slider if they closed out and came back in between buying
            var slider = $target.find('.slider');
            if(slider.height() > 0){
                slider.css({height: 0, display: 'block'});
                slider.animate({height: 65}, 200);
            }
        });  
    },
    findCombos: function( combs ){
        // Figure out the minimum cost
        var minCost = 100;
        for (var i in combs) {
            var combo = combs[i];
            var cost = 0;
            if (typeof combo.left !== 'undefined')
                cost = combo.left + combo.right;
            if(cost < minCost) minCost = cost;
        }

        // Filter args.combs based on cost and then sort the list
        var combos = [];
        for(var i in combs){
            var combo = combs[i];
            var cost = combo.left + combo.right;
            if(cost <= minCost + 1){
                combos.push(combo);
            }
            combo.index = i;
        }
        // Sort the combinations with least $ for left first
        combos.sort( function(a, b){ return a.left < b.left; } );
        return { combos: combos, minCost: minCost };
    },
    robotSelectCard: function( div, action, possibilities ){
        var self = this;
        var when = Math.floor( 2000 + Math.random()*1000 );
        window.setTimeout( function () {  
            // if this is nto here, chrome do not fire the other timeout ???
        }, 10 );
        window.setTimeout( function () {    
            var index;
            self.showSelectCard( div );
            div.data('state', action);
            if ( action !== "trashing" ){
                var c = self.findCombos( possibilities );
                var index = c.minCost>0 ? c.combos[0].index : 0;                      
            } else {
                index = 0;
            }
            self.chooseCard(div, index);
        }, when);
    },
    // handle all the different messages sent from the server
    onMessage: function(args, msg){
        switch(args.messageType){
            // we're dealt a new hand
            case 'hand':
                args.cards = $.map(args.cards || {}, function(k,v){ return [k]; });
                if ( this.robot ){
                    $(".robotinfo").remove();                              
                    var selectedCard = args.selected;
                    var txt = args.action === 'buying' ? selectedCard : args.action;
                    $(".wonderBoard").append('<div class="robotinfo" style="margin-top: 35px; text-align: right; color: white;">'+Math.floor(args.wonder.value*10)/10+'</div>');
                    $(".wonderBoard").append('<div class="robotinfo" style="margin-top: 5px; text-align: right; color: white;">'+txt+'</div>');                    
                }
                
                this.cleanHand();
                var self = this;			
				
				// Insert new hand into the board
                var count = args.cards.length;
                for(i in args.cards){
                    var card = args.cards[i];                    
                    var div = this.cardDiv(count, card);
                    
                    if ( this.robot ){
                        div.append("<div class='robotinfo' style='margin-top: 200px;margin-left: 50px;'>" + Math.floor(card.value*10)/10 + "</div>");
                        if ( args.selected === card.name ){                          
                            this.robotSelectCard( div, args.action, args.action === 'buying' ? card.possibilities : args.wonder.possibilities );                            
                        }
                    }
                    $('#game').append(div);
                    count--;
                }

                // One liner to get the index of a card based on its id
                function cardIndex(card){
                    return parseInt($(card).attr('id').substring(4,5)) - 1;
                }

                // Put new cards at start position and rotate them accordingly
                var numCards = args.cards.length;
                $('#game').css('overflow', 'hidden');
                $('.card').each(function(){
                    if($(this).hasClass('ignore')) return;
                    var deg = (cardIndex(this) + 0.5 - numCards / 2) * 6;
                    $(this).css({
                        'left': self.wonder.wonderDiv.offset().left / self.scale + self.wonder.wonderDiv.width() / 2 - 75,
                        'bottom': -200
                    });
                    $(this).rotate(deg);
                    $(this).data('rotation', deg);
                })

                $('.card:not(.ignore)').each(function(){
                    var index = cardIndex(this);
                    // send card to appropriate position on the main board
                    $(this).animate({
                        'bottom': '+=' + ((Math.pow(index + 0.5 - numCards / 2, 2) * -6) + 635),
                        'left': '+=' + (index + 0.5 - numCards / 2) * 120
                    }, 1500, 'easeOutExpo', function(){
                        // we overflow hidden when dealing to avoid troubles where cards start outside the screen
                        $('#game').css("overflow", "auto");
                    });
                });

                // card blow up animation (on click)
                $('.card:not(.ignore)').click(function(e){
                    e.stopPropagation();
                    // don't let the player blow up cards which are moving currently or shouldn't be blown up
                    if($(this).is(':animated') || $(this).hasClass('ignore')) return;
                    // if we're clicking on a blown up card, shrink it back down
                    if($(this).hasClass('selected')){
                        self.shrinkCard(this);
                        $('.card:not(.ignore)').animate({ opacity: 1 }, 200);
                    } else {
                        // shrink a selected card if it exists
                        var selected = $('.card.selected');
                        self.shrinkCard(selected);

                        self.showSelectCard( $(this) );                        
                    }
                });

                $('.card').each(function(_, card) {
                    self.resetCard($(card));
                });
                break;

            case 'cardschosen':
                // Push cards into the left/right columns if our neighbors played any
                if (args.left)
                    this.updateColumn('left', args.left.color,
                                      this.cardImageFromName(args.left.name), 200);
                if (args.right)
                    this.updateColumn('right', args.right.color,
                                      this.cardImageFromName(args.right.name), 200);
                break;

            case 'coins':
                this.wonder.updateCoins(args.data);
                break;

            case 'military':
                this.wonder.updateMilitary(args);
                break;

            case 'possibilities':
                var card = $('.card.selected');
                var showfree = this.hasfree && card.data('state') != 'building';
				
                // If this is impossible to play, throw up a message saying so
                // and don't allow a click to play it.
                if (!args.combs[0] && !showfree) {
					card.append('<div class="overlay"><h2>Error</h2>You cannot complete that action</div>');
                    card.find('.overlay').animate({ opacity: '0.9' }, 200);
                    card.find('img').animate({opacity: '0.3'}, 200);
                    var removeErr = function(card){
                        card.find('.overlay').animate({ opacity: 0 }, 200, function(){
                            $(this).remove();
                        });
                        card.find('img').css('opacity', '1');
                    }
                    card.find('.overlay').click(function(e){ e.stopPropagation(); removeErr(card); })
                    setTimeout(function(){ removeErr(card) }, 2000);
                    return;
                }

                var c = this.findCombos( args.combs );
                var combos = c.combos;
                var minCost = c.minCost;
								
				// If it's a free card, then we just chose it
                if (minCost == 0) {
                    this.chooseCard(card, 0);
                    return;
                }
				
                // find the first element in the array with the minimum cost
                // this'll be the default combo displayed on the slider
                var firstMin = 0;
                for(var i = 0; i < combos.length; i++){
                    if(combos[i].left + combos[i].right == minCost){
                        firstMin = i; break;
                    }
                }

                var card = $('.card.selected');
                var slider = card.find('.slider');
                var hidden;
                if (showfree && combos.length == 0)
                    hidden = '.options a:not(.wonder)';
                else if (showfree)
                    hidden = '.options .trash';
                else
                    hidden = '.options a:not(.play)';

                // hide the appropriate options based on wonder and # of combinations
                card.find(hidden).animate({opacity: 0}, 200, function() {
                    $(this).css('visibility', 'hidden');
                });

                var self = this;
                // if the card isn't free (buy combinations exist...)
                if (combos.length > 0) {
                    // set the default value to the first minimum we found
                    slider.find('.left').html(combos[firstMin].left);
                    slider.find('.right').html(combos[firstMin].right);
                    slider.find('input[type=range]').attr('max', combos.length - 1)
                                                    .attr('value', firstMin);
                    // show the slider div
                    slider.click(function(e){ e.stopPropagation(); })
                        .css({'height': '0', display: 'block'})
                        .animate({
                            height: 65
                        }, 200);
                    // have the slider update the #s to left and right on change
                    card.find('input[type=range]').change(function(){
                        var val = $(this).attr('value');
                        slider.find('.left').html(combos[val].left);
                        slider.find('.right').html(combos[val].right);
                    });
         
                    // Change the play button to a buy button
                    card.find('.play').animate({opacity: 0}, 200, function() {
                        $(this).addClass('buy')
                               .animate({opacity: 1}, 200)
                               .unbind('click')
                               .click(function(e){
                                   e.stopPropagation();
                                   var combo = combos[slider.find('input[type=range]').attr('value')];
                                   self.chooseCard(card, combo.index);
                                   card.find('.slider').animate({height: 0}, 200, function(){
                                       $(this).css('display', 'none');
                                   });
                                   return false;
                               });
                    });
                }

                // if the wonder allows us to buy a card for free
                if (showfree) {
                    card.find('.wonder').animate({opacity: 0}, 200, function() {
                        $(this).addClass('free')
                               .animate({opacity: 1}, 200)
                               .unbind('click')
                               .click(function(e){
                                   self.chooseCard(card, -1);
                                   card.find('.slider').animate({height: 0}, 200, function(){
                                       $(this).css('display', 'none');
                                   });
                                   return false;
                               });
                    });
                }
                break;

            case 'error':
                // todo: more fancy alerts
                if($('.card.selected').length){

                }   else {
                    console.log(args.data)
                }
                break;

            case 'freecard':
                this.hasfree = args.hasfree;
                break;
           
            case 'discard':
                if(args.cards != null && args.cards.length > 0){
                    this.showCardSelect(args, true);
                }
                break;

            case 'playerinfo':
                // when we send a request for playerinfo by id, we get back an array of info
                // args.cards = played cards, args.coins = current coins, args.wonder = wonder info
                // args.wonder has args.wonder.name and args.wonder.stage
                console.log("Received playerinfo", args);
                this.showCardSelect(args, false);
                break;

            case 'neighborwonders':
                this.neighbors.left.resource = args.left.resource;
                this.neighbors.right.resource = args.right.resource;
                this.neighbors.left.stage = 0;
                this.neighbors.right.stage = 0;
                this.neighbors.left.wonder = args.left.wonder;
                this.neighbors.right.wonder = args.right.wonder;
                this.showWonderResources();
                break;

            case 'builtwonder':
                for(i in this.neighbors){
                    if(this.neighbors[i].id == args.id){
                        $('.neighbor.' + i + ' .info div.stage').css('background-image', 'url(images/tokens/pyramid-stage' + args.stage + '.png)');
                    }
                }
                break;

            case 'scores':
                this.cleanHand();
                // todo: make scoreboard better looking
                $('#setup-container').fadeIn(1000);
                $('#setup :not(h1)').remove();
                $('#setup h1').html('SCORES');
                var fields = ['name', 'red', 'coins', 'wonder', 'blue', 'yellow', 'purple', 'green', 'total']
                $('#setup').append('<table id="scores"></table>');     
                for(var i = 0; i < fields.length; i++){
                    var field = fields[i];
                    var tr = $('<tr></tr>');
                    tr.append('<th class="' + field + '">' + (field == 'total' ? '&Sigma;' : '') + '</th>');
                    for(var j = 0; j < this.players.length; j++){
                        var pl = this.players[j];
                        if(field == 'name') tr.append('<td>' + pl.name + '</td>');
                        else tr.append('<td>' + args[pl.id][field] + '</td>');
                    }
                    $('#scores').append(tr);
                }
                if ( this.robot ){
                    // reload the page after 2s
                    setTimeout( function(){ location.reload(); }, 2000);                     
                }
                break;

            default:
                console.log(args, msg);
                break;
        }
    }
}

String.prototype.capitalize = function() {
    return this.charAt(0).toUpperCase() + this.slice(1);
}
