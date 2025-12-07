<?php

namespace PrintfulIntegration\Printful\Exceptions;

class PrintfulApiException extends PrintfulException {
	
	public function __construct( $message, $code = 0, Exception $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}
}
