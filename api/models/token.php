<?php
/**
 * @SWG\Model(id="token",required="token")
 */
class token
{
	function __construct($token) {
        $this->token = $token;
    }
	
    /**
     * @SWG\Property(name="token",type="string",description="Clients autorization token")
     */
    public $token;

}
