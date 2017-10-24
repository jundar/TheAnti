<?php

namespace TheAnti\GameElement;

/*
 * The board class represents a board in Texas Hold'em.
 * This is pretty much a wrapper for 0, 3, 4, or 5 cards.
 * But it adds some nice validation and features.
 */
class Board
{
	//@var Card[] $cards
	protected $cards = [];

	/*
	 * Creates a new board from an optional array of cards.
	 */
	public function __construct(array $cards = [])
	{
		$this->addCards($cards);
	}

	/*
	 * Adds cards to the board.
	 * Must result in a valid board.
	 */
	public function addCards(array $cards)
	{
		//Add each card
		foreach($cards as $card)
		{
			$this->addCard($card);
		}

		//Verify that this operation resulted in a valid board
		$count = count($cards);
		if(!in_array($count, [0, 3, 4, 5]))
		{
			throw new \Exception("Can't create a board with $count cards!");
		}
	}

	/*
	 * Adds a single card to the baord.
	 */
	protected function addCard(Card $card)
	{
		$this->cards[] = $card;
	}

	/*
	 * Gets the cards on the board.
	 */
	public function getCards(): array
	{
		return $this->cards;
	}
}