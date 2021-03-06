<?php

use Codebird\Codebird;
use MonkeyLearn;

require 'vendor/autoload.php';

//Database connection

$db = new PDO('mysql:host=localhost;dbname=sentibot', 'sentibot', 'sentibot');

//Codebird setup
Codebird::setConsumerKey(CONSUMER_KEY, CONSUMER_SECRET);

//Instance of codebird
$cb = Codebird::getInstance();

$cb->setReturnFormat(CODEBIRD_RETURNFORMAT_ARRAY);

//Set token
$cb->setToken(TOKEN_KEY, TOKEN_SECRET);


//Get latest tweet mention
$sql =  $db->query("
			SELECT *
			FROM tracking
			ORDER BY twitter_id DESC
			LIMIT 1");

$lastId = $sql->fetch(PDO::FETCH_OBJ);

//To prevent adding duplicate mentione tweets by taking since_id only
//Source: https://dev.twitter.com/rest/reference/get/statuses/mentions_timeline (since_id)
$lastId = $lastId ? 'since_id='.$lastId->twitter_id : '';

$mentions = $cb->statuses_mentionsTimeline($lastId);

//If no mentions in Tweet, then return
if (!isset($mentions[0])) {
	echo 'No tweets added!';
	return;
}


	//Collect the tweets and store in $tweets/tweetsText array
	$tweets = [];
	foreach ($mentions AS $mention) {

		/* 
		If there are mentions, then store its ID
		We are using the main 'id' as a primary key or indicator, then something with
		an id must be a Twitter mention.
		*/
		if (isset($mention['id'])) { 
			$tweets[] = [
				'id' => $mention['id'],
				'user_screen_name' => $mention['user']['screen_name'],
				'text' => $mention['text']

			];
		} 

		//To send to MonkeyLearn, just extract the 'text' only
		$tweetsText = array_map(function($tweet){ 
			return $tweet['text'];
		}, $tweets);
	}

//Now with the tweets stored in as an array, let's pass it to MonkeyLearn to classify it
//[negative, positive, netural]
$ml = new MonkeyLearn\Client(MONKEYLEARN_KEY);
$module_id = 'cl_qkjxv9Ly';
$tweetAnalysis = $ml->classifiers->classify($module_id, $tweetsText, true);

//Assign the sentiment classfier results onto a variable
$tweetAnalysisResults = $tweetAnalysis->result;


//Loop through the mentioned tweets
//Assign the emoji based on the sentiment category (positive, negative, neutral)
foreach ($tweets AS $index=>$tweet) {

	switch ($type = $tweetAnalysisResults[$index][0]['label'])
	{
		case 'positive':
			$emojiSet = getEmojis($type);
		break;

		case 'negative':
			$emojiSet = getEmojis($type);
		break;

		default:
			$emojiSet = getEmojis('neutral');
		break;
	}

	//Start tweeting the user (using Codebird API)
	//Add sentitment emoji depending on the tone of the tweet (based on ML API)
	$cb->statuses_update([
		'status' => '@'.$tweet['user_screen_name'] . ''.html_entity_decode($emojiSet[rand(0, count($emojiSet)-1)], 0, 'UTF-8'),
		'in_reply_to_status_id' => $tweet['id']
		]);

	//Store/track tweets that we already replied to
	$sql = $db->prepare("INSERT INTO tracking (twitter_id) VALUES (:twitterId)");

	$result = $sql->execute([
		'twitterId' => $tweet['id']
		]);


}

//Success!
echo 'tweets added!';












