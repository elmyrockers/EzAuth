<?php
namespace elmyrockers;






interface EzAuthRememberMeInterface {
	public function generateRememberMeToken( $user );
	public function checkRememberMeToken();
}