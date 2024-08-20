<?php
namespace elmyrockers;

use elmyrockers\EzAuthRememberMeInterface;





class EzAuthRememberMe implements EzAuthRememberMeInterface
{
	public function generateToken( $user )
	{
		echo "hai apa kabar... token generated";
	}

	public function verifyToken()
	{
		
	}
}