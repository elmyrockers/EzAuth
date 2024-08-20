<?php
namespace elmyrockers;

use elmyrockers\EzAuthRememberMeInterface;





class EzAuthRememberMe implements EzAuthRememberMeInterface
{
	private $config;
	public function __construct( $config )
	{
		$this->config = $config;
	}

	public function generateToken( $user )
	{
		echo "hai apa kabar... token generated";
	}

	public function verifyToken()
	{
		
	}
}