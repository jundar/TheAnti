<?php

namespace TheAnti\HandStrength;

use TheAnti\GameElement\Card;
use TheAnti\GameElement\Board;
use TheAnti\GameElement\Hand;
use TheAnti\Range\Range;

/*
 * Calculates the hand strength of all hands.
 * Can be given an optional board with 3, 4, or 5 cards.
 */
class HandStrengthCalculator
{
	//@var string The path to the folder to store range files.
	protected $rangeFolder = "C:\\xampp\\htdocs\\poker\\TheAnti\\tool\\ranges";

	//@var string The name of the executable to calculate hand equities.
	protected $ompEval = "OMPEval.exe";

	//@var Board The board.
	protected $board = NULL;

	//@var Range The range to calculate strength for.
	protected $range = NULL;

	//@var array Mapping of hands to equities.
	protected $handEquities = [];

	//@var bool Indicates whether we're in a calculated state or not.
	protected $calculated = false;

	/*
	 * Creates a new hand strength calculator based on an optional board.
	 * Accepts an array of Card objects.
	 */
	public function __construct(Board $board, Range $range)
	{
		$this->setRange($range);

		$this->board = $board;
	}

	/*
	 * Sets/overwrites the range we're working with.
	 * Destroys the array of calculated equities
	 */
	public function setRange(Range $range)
	{
		$this->range = $range;
		$this->clearCalculation();
	}

	/*
	 * Calculates equities for all hands in range.
	 */
	public function calculate(): bool
	{
		$this->clearCalculation();

		//Generate file for hands in our range
		$rangeContents = "";
		foreach($this->range->getHands() as $hand)
		{
			$rangeContents .= $hand->toString() . "\n";
		}
		file_put_contents($this->rangeFolder . "\\range_hands.txt", chop($rangeContents));

		//Build board string
		$board = "";
		foreach($this->board->getCards() as $card)
		{
			$board .= $card->toString();
		}

		$url = "http://localhost/poker/TheAnti/tool/generate_equities.php?omp={$this->ompEval}&handFile={$this->rangeFolder}\\range_hands.txt&board={$board}";

		$equities = file_get_contents($url);

		if($equities)
		{
			$lines = explode("\n", $equities);
			foreach($lines as $line)
			{
				//Get the data from the evaluator
				$handEquities = explode(",", $line);
				$handString = $handEquities[0];
				$handWins = (int) $handEquities[1];
				$handTies = (int) $handEquities[2];
				$handTotal = (int) $handEquities[3];

				//Create the object to represent the hand's strength
				$hand = Hand::importFromString($handString);
				$handStrength = new HandStrength($hand, $handWins, $handTies, $handTotal);

				//Map the hand strength to the percentage it wins
				$this->handEquities[$handString] = $handStrength;
			}

			$this->rankHandStrength();

			return $this->calculated = true;
		}

		else
		{
			return false;
		}
	}

	/*
	 * Indicates whether we can get already calculated equities.
	 */
	public function isCalculated(): bool
	{
		return $this->calculated;
	}

	/*
	 * Gets all hands within a certain strength percentage within our range.
	 * For example, if we want to get the top 15% of hands within our range,
	 * we would specify:
	 * min = 0.85, max = 1.0
	 * @return Hand[]
	 */
	public function getHandsByStrength(float $min = 0.0, float $max = 1.0): array
	{
		//This is really slow, but simple
		$hands = [];
		foreach($this->handEquities as $handStrength)
		{
			$strength = $this->getHandStrength($handStrength->getHand());
			if($strength >= $min && $strength <= $max)
			{
				$hands[] = $handStrength->getHand();
			}
		}
		return $hands;
	}

	/*
	 * Gets the array of calculated equities for each hand in range.
	 */
	public function getRangeStrength(): array
	{
		return $this->handEquities;
	}

	/*
	 * Gets the strength of a hand based on its position in the array of equities.
	 * This just tells you the offset divided by the total, which is a bit too
	 * simple as many hands will have very similar equities.
	 * @return float A number between 0 and 1
	 * The closer to 1 the stronger the hand as it's basically a percent indicating
	 * how strong our hand is.
	 * A negative indicates that the hand was not in our range.
	 * Should this be an exception?
	 * This method could use to be improved significantly.
	 * It should almost be its own class.
	 */
	public function getHandStrength(Hand $hand): float
	{
		$handString = $hand->toString();
		if(!isset($this->handEquities[$handString]))
		{
			return -3.14;
		}

		else
		{
			//If we can't lose, this needs to be considered the nuts
			$handStrength = $this->handEquities[$handString];
			if($handStrength->getLoss() == 0.0)
			{
				return 1;
			}

			//Regular algorithm
			else
			{
				$len = count($this->handEquities);
				$offset = array_search($handString, array_keys($this->handEquities));
				return round($offset / $len, 2);
			}
		}
	}

	/*
	 * Orders the array from low to high in hand strength based on win percentages.
	 */
	protected function rankHandStrength()
	{
		uasort($this->handEquities, function(HandStrength $a, HandStrength $b){
			return $a->getWin() <=> $b->getWin();
		});
	}

	/*
	 * Clears the array of calculated equities sets our calculated status to false.
	 */
	protected function clearCalculation()
	{
		$this->handEquities = [];
		$this->calculated = false;
	}
}