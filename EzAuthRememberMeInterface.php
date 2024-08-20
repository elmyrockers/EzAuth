<?php
namespace elmyrockers;






interface EzAuthRememberMeInterface {
	public function generateToken( $user );
	public function verifyToken();
}