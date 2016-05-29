<?php
 /*
 * 
 * bouncehandler.php | MailWizz / PowerMTA / Webhook bounce handler
 * Copyright (c) 2016 Gerd Naschenweng / bidorbuy.co.za
 * 
 * The MIT License (MIT)
 *
 * @author Gerd Naschenweng <gerd@naschenweng.info>
 * @link http://www.naschenweng.info/
 * @copyright 2016 Gerd Naschenweng  http://github.com/magicdude4eva
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */
 
$log->lwrite('Bounce-provider: MailWizz, initialising');

// ------------------------------------------------------------------------------------------------------
// **** Initialise MailWizz
$MAILWIZZ_HANDLER_ENABLED = false;

if (defined('MAILWIZZ_API_PUBLIC_KEY') && MAILWIZZ_API_PUBLIC_KEY && 
    defined('MAILWIZZ_API_PRIVATE_KEY') && MAILWIZZ_API_PRIVATE_KEY &&
    defined('MAILWIZZ_ENDPOINT_URL') && MAILWIZZ_ENDPOINT_URL) {
    $log->lwrite('   Endpoint-URL=' . MAILWIZZ_ENDPOINT_URL);
    require_once dirname(__FILE__) . '/MailWizzApi/Autoloader.php';
    
    MailWizzApi_Autoloader::register();
    
    // configuration object
    $config = new MailWizzApi_Config(array('apiUrl' => MAILWIZZ_ENDPOINT_URL, 'publicKey' => MAILWIZZ_API_PUBLIC_KEY, 'privateKey' => MAILWIZZ_API_PRIVATE_KEY,
      'components' => array('cache' => array('class'     => 'MailWizzApi_Cache_File',
        'filesPath' => dirname(__FILE__) . '/MailWizzApi/Cache/data/cache', // make sure it is writable by webserver
       ) ), ));
       
    // now inject the configuration and we are ready to make api calls
    MailWizzApi_Base::setConfig($config);
    
    $MailWizzEndPoint = new MailWizzApi_Endpoint_ListSubscribers();
    
    $log->lwrite('   MailWizz enabled!');

    $MAILWIZZ_HANDLER_ENABLED = true;
} else {
$log->lwrite('   Skipped - not configured!');
}

$log->lwrite('Bounce-provider: MailWizz, complete');


// ========================================================================================================
// MAILWIZZ FUNCTIONS
// Handle MailWizz unsubscribe
function MailWizz_unsubscribeRecipient($recipient) { 
	global $log, $MailWizzEndPoint;
	
	if (MAILWIZZ_HANDLER_ENABLED == false) {
		return;
	}

	// Check if subscriber exists
	$response = $MailWizzEndPoint->emailSearchAllLists($recipient, $pageNumber = 1, $perPage = 30);
	
	if ($response->body['status'] == "success" && $response->body['data']['count'] > 0) {
		$log->lwrite('  MailWizz: unsubscribe for ' . $recipient . ':');
		foreach ($response->body['data']['records'] as $subscription) {
			if ($subscription['status'] != "unsubscribed") {
				$unsubscriberesponse = $MailWizzEndPoint->unsubscribe($subscription['list']['list_uid'], $subscription['subscriber_uid']);
				$log->lwrite('   - ' . $unsubscriberesponse->body['status'] . ': ' . $subscription['list']['name']);
			} else {
				$log->lwrite('   - skipped: ' . $subscription['list']['name']);
			}
		}
	} else {
		if ($response->body['status'] != "success") {
			$log->lwrite('  MailWizz: Failed looking up record ' . $recipient . ' with status=' . $response->body['status']);
		} else if ($response->body['data']['count'] == 0) {
			$log->lwrite('  MailWizz: Skipping ' . $recipient . ', already unsubscribed!');
		}
	}

    return ; 
}
