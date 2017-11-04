<?php

namespace TheAnti\Match;

use TheAnti\GameElement\Board;
use TheAnti\GameElement\Deck;
use TheAnti\GameElement\Hand;
use TheAnti\HandStrength\WinnerCalculator;
use TheAnti\Player\Player;

/*
 * This class represents a round of Texas Hold'em.
 */
class Round
{
	//@var Match The match we're in
	protected $match = NULL;

	/*
	 * @var array The action that has taken place this round.
	 * This is really only an array of integers, where each
	 * one represents the amount of money that a player put
	 * into the pot.
	 * [1, 2] is how it always starts as this is for the blinds.
	 * If one player puts money into the pot, and the next player puts in 0,
	 * that means they folded.
	 * If a player puts 0 in any other circumstances, it means
	 * that they checked.
	 */
	protected $actions = [];

	//@var Board The board.
	protected $board = NULL;

	//@var int The pot.
	protected $pot = 0;

	//@var Deck The deck for this round.
	protected $deck = NULL;

	/*
	 * Creates a new round.
	 */
	public function __construct(Match $match)
	{
		$this->match = $match;
		$this->board = new Board();
		$this->deck = new Deck();
	}

	/*
	 * Starts the round!
	 */
	public function start()
	{
		print "\n";

		print "Starting round...\n";

		//Shuffle the deck
		$this->deck->shuffle();

		//Post the blinds
		$this->postBlinds();

		//Deal cards
		$this->dealCards();

		//Preflop action
		/*
		while($this->actionIsNeeded())
		{
			$this->getAction();
		}
		*/

		//Flop action
		$this->burnAndTurn(3);
		/*
		while($this->actionIsNeeded())
		{
			$this->getAction();
		}
		*/

		//Turn action
		$this->burnAndTurn();
		/*
		while($this->actionIsNeeded())
		{
			$this->getAction();
		}
		*/

		//River action
		$this->burnAndTurn();
		/*
		while($this->actionIsNeeded())
		{
			$this->getAction();
		}
		*/

		//Award pot to winning player(s)
		$this->awardPot();

		//Update player positions
		$this->match->moveButton();

		print "Round over.\n";

		print "\n";
	}

	protected function postBlinds()
	{
		print "\n";

		//Get blinds
		$settings = $this->match->getSettings();
		$blinds = $settings->getBlinds();

		//Send messages to players
		$players = $this->match->getPlayers();
		$players[0]->broadcast("posts \${$blinds[1]} SB.");
		$players[1]->broadcast("posts \${$blinds[0]} BB.");

		//Add blinds from stack to pot
		$this->addToPot(
			$this->getMoneyFromPlayer($players[0], $blinds[1])
			+
			$this->getMoneyFromPlayer($players[1], $blinds[0])
		);

		//Update actions
		$this->actions[] = 1;
		$this->actions[] = 2;

		//Update status
		print "Pot is now \${$this->pot}.\n";

		print "\n";
	}

	/*
	 * Deals the cards.
	 */
	protected function dealCards()
	{
		//Get the hands
		$hands = [
			new Hand($this->deck->getCards(2)),
			new Hand($this->deck->getCards(2))
		];

		foreach($hands as $key => $hand)
		{
			$this->match->getPlayers()[$key]->broadcast("Gets dealt hand " . $hand->toString() . ".");
			$this->match->getPlayers()[$key]->setHand($hand);
		}
	}

	/*
	 * Awards the pot to the winning player(s).
	 */
	public function awardPot()
	{
		//Get the players
		$players = $this->match->getPlayers();

		$winnerCalculator = new WinnerCalculator($players[0]->getHand(), $players[1]->getHand(), $this->board);
		$winner = $winnerCalculator->calculate();

		//It's a tie
		if($winner == -1)
		{
			foreach($this->match->getPlayers() as $player)
			{
				$stack = $player->getStack();
				$stack += $this->pot / 2;
				$player->setStack($stack);
				$player->broadcast("Wins $" . ($this->pot / 2) . ".");
			}
		}

		//The higher hand wins
		else
		{
			$stack = $players[$winner]->getStack();
			$stack += $this->pot;
			$players[$winner]->setStack($stack);
			$players[$winner]->broadcast("Wins $" . ($this->pot) . ".");
		}

		$this->pot = 0;
	}

	/*
	 * Burns and turns cards.
	 */
	public function burnAndTurn(int $cards = 1)
	{
		//Burn
		$this->deck->getCard();

		//Turn cards
		$this->board->addCards($this->deck->getCards($cards));

		//Display board
		print "Board: " . $this->board->toString() . "\n";
	}

	/*
	 * Adds money to the pot.
	 */
	protected function addToPot(int $amount): int
	{
		$this->pot += $amount;
		return $amount;
	}

	/*
	 * Gets money from a player.
	 */
	protected function getMoneyFromPlayer(Player $player, int $amount): int
	{
		$stack = $player->getStack();
		$stack -= $amount;
		$player->setStack($stack);
		return $amount;
	}
}