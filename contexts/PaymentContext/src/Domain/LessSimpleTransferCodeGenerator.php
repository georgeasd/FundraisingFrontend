<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\PaymentContext\Domain;

/**
 * @licence GNU GPL v2+
 */
class LessSimpleTransferCodeGenerator implements TransferCodeGenerator {

	private $characterSource;

	public function __construct( \Iterator $characterSource ) {
		$this->characterSource = $characterSource;
	}

	public function generateTransferCode(): string {
		$code = $this->generateCode();
		return $code . '-' . $this->createCheckSum( $code );
	}

	public function generateCode(): string {
		return $this->getCharacter()
			. $this->getCharacter()
			. $this->getCharacter()
			. $this->getCharacter()
			. '-'
			. $this->getCharacter()
			. $this->getCharacter()
			. $this->getCharacter()
			. $this->getCharacter();
	}

	private function getCharacter(): string {
		$character = $this->characterSource->current();
		$this->characterSource->next();
		return $character;
	}

	private function createCheckSum( string $code ): string {
		return strtoupper( substr( md5( $code ), 0, 2 ) );
	}

}