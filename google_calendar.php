<?php

/**
 * Google calendar wrapper class
 *
 * @author Sam Tubbax <sam@sumocoders.be>
 */
class GoogleCalendar
{
	const CALENDAR_SCOPE = 'https://www.googleapis.com/auth/calendar';
	const OAUTH_URL = 'https://accounts.google.com/o/oauth2';
	const API_URL = 'https://www.googleapis.com/calendar/v3/calendars';
	const DEBUG = true;

	/**
	 * Textual properties
	 *
	 * @var string
	 */
	public $user, $password, $accessToken, $refreshToken, $calendar;

	/**
	 * Delete an event
	 *
	 * @param	string $id		The id of the event
	 */
	public function deleteEvent($id)
	{
		$this->doCall('events/' . $id, null, 'DELETE');
	}

	/**
	 * Make the call
	 *
	 * @param	string $url							The URL to call.
	 * @param	array $parameters					The parameters that should be passes.
	 * @param	string[optional] $method			Which method should be used?
	 * @return	array
	 */
	private function doCall($url, array $parameters = null, $method = 'GET')
	{
		// redefine
		$url = (string) $url;
		$method = (string) $method;

		// init var
		$queryString = '';

		// through GET
		if($method == 'GET')
		{
			// append to url
			if(!empty($parameters)) $url .= '?' . http_build_query($parameters, null, '&');
			$options = array();
		}

		// through POST
		elseif($method == 'POST')
		{
			$options[CURLOPT_CUSTOMREQUEST] = 'post';
			$data = json_encode($parameters);
			$options[CURLOPT_POSTFIELDS] = $data;
			$options[CURLOPT_HTTPHEADER] = array(
			    'Content-Type: application/json',
			    'Content-Length: ' . strlen($data)
			);
		}
		else $options[CURLOPT_CUSTOMREQUEST] = $method;

		if($this->calendar == null) throw new GoogleCalendarException('No Calendar set');

		// prepend
		$url = self::API_URL . '/' . $this->calendar . '/' . $url;

		if(strpos($url, '?') != false) $url .= '&access_token=' . $this->accessToken;
		else $url .= '?access_token=' . $this->accessToken;

		// get response
		$response = self::getContent($url, $options);

		// we expect JSON so decode it
		$json = @json_decode($response, true);

		// validate json
		if($json === false) throw new GoogleCalendarException('Invalid JSON-response');

		// is error?
		if(isset($json['error']))
		{
			if(self::DEBUG)
			{
				echo '<pre>'."\n";
				var_dump($headers);
				var_dump($json);
				echo '</pre>'."\n";
			}

			// init var
			$type = (isset($json['error']['type'])) ? $json['error']['type'] : '';
			$message = (isset($json['error']['message'])) ? $json['error']['message'] : '';

			// build real message
			if($type != '') $message = trim($type . ': ' . $message);

			// throw error
			throw new GoogleCalendarException($message);
		}

		// return
		return $json;
	}

	/**
	 * Get all events
	 *
	 * @return array
	 */
	public function getAllEvents()
	{
		return $this->doCall('events');
	}

	/**
	 * Get events between 2 timestamps
	 *
	 * @param	int $from	The from timestamp.
	 * @param	int $to		The to timestamp.
	 * @return array
	 */
	public function getBetween($from, $to)
	{
		$from = (int) $from;
		$to = (int) $to;

		$from = date('Y-m-d', $from) . 'T' . date('H:i:s', $from) . '.000z';
		$to = date('Y-m-d', $to) . 'T' . date('H:i:s', $to) . '.000z';

		return $this->doCall('events', array('timeMax' => $to, 'timeMin' => $from));
 	}

	/**
	 * Get content from an URL.
	 * BLATANTLY stolen from Spoon Library
	 *
	 * @return	string							The content.
	 * @param	string $URL						The URL of the webpage that should be retrieved.
	 * @param	array[optional] $cURLoptions	Extra options to be passed on with the cURL-request.
	 */
	public static function getContent($URL, array $cURLoptions = null)
	{
		// check if curl is available
		if(!function_exists('curl_init')) throw new GoogleCalendarException('This method requires cURL (http://php.net/curl), it seems like the extension isn\'t installed.');

		// set options
		$options[CURLOPT_URL] = (string) $URL;
		$options[CURLOPT_USERAGENT] = 'GoogleCalendar PHPclass';
		if(ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) $options[CURLOPT_FOLLOWLOCATION] = true;
		$options[CURLOPT_RETURNTRANSFER] = true;
		$options[CURLOPT_TIMEOUT] = 10;
		$options[CURLOPT_SSL_VERIFYHOST] = false;
		$options[CURLOPT_SSL_VERIFYPEER] = false;

		// any extra options provided?
		if($cURLoptions !== null)
		{
			// loop the extra options
			foreach($cURLoptions as $key => $value) $options[$key] = $value;
		}

		// init
		$curl = curl_init();

		// set options
		curl_setopt_array($curl, $options);

		// execute
		$response = curl_exec($curl);

		// fetch errors
		$errorNumber = curl_errno($curl);
		$errorMessage = curl_error($curl);

		// close
		curl_close($curl);

		// validate
		if($errorNumber != '') throw new GoogleCalendarException($errorMessage);

		// return the content
		return (string) $response;
	}

	/**
	 * Get an event by its id
	 *
	 * @param	string $id		The event id.
	 * @return array
	 */
	public function getEvent($id)
	{
		try
		{
			return $this->doCall('events/' . $id);
		}
		catch(GoogleCalendarException $e)
		{
			if($e->getMessage() == '') return null;
		}
	}

	/**
	 * Get all the events after a specific timestamp
	 *
	 * @param	int $from	The timestamp.
	 * @return array
	 */
	public function getFrom($from)
	{
		$from = (int) $from;

		$from = date('Y-m-d', $from) . 'T' . date('H:i:s', $from) . '.000z';

		return $this->doCall('events', array('timeMin' => $from));
	}

	/**
	 * Exchange OAuth Acces thing with a code
	 *
	 * @param	string[optional] $code		The da vinci code.
	 * @param	string[optional] $refreshToken	The refresh code.
	 * @return void
	 */
	public function getOAuthAcces($code = null, $refreshToken = null)
	{
		$params = array();
		if($code != null) $params['code'] = $code;
		if($refreshToken != null) $params['refresh_token'] = $refreshToken;
		$params['client_id'] = '720048534826.apps.googleusercontent.com';
		$params['client_secret'] = 'Y6nMZo1DL0t1Y9XVaAET-TTE';
		if($code != null) $params['redirect_uri'] = 'http://cardiologiedenef.sumocoders.eu/backend/cronjob.php?action=get_data&module=calendar';
		if($code != null) $params['grant_type'] = 'authorization_code';
		else $params['grant_type'] = 'refresh_token';

		$options = array(CURLOPT_POSTFIELDS => http_build_query($params), CURLOPT_POST => count($params));

		$response = json_decode(self::getContent(self::OAUTH_URL . '/token', $options));

		foreach($response as $key=>$value)
		{
			if($key == 'access_token') $this->accessToken = $value;
			if($refreshToken == null && $key == 'refresh_token') $this->refreshToken = $value;
		}
	}

	/**
	 * Get all the events before a specific timestamp
	 *
	 * @param	int $to	The timestamp.
	 * @return array
	 */
	public function getTo($to)
	{
		$to = (int) $to;

		$to = date('Y-m-d', $to) . 'T' . date('H:i:s', $to) . '.000z';

		return $this->doCall('events', array('timeMax' => $to));
	}

	/**
	 * Insert an event
	 *
	 * @param	array $values		The values to use
	 * @return void
	 */
	public function insertEvent($values)
	{
		$this->doCall('events', $values, 'POST');
	}

	/**
	 * Do the OAuth Dance
	 *
	 * @param	string $redirectUrl					The redirect URL.
	 * @param	string[optional] $refreshToken		The refresh Token.
	 * @return void
	 */
	public function requestOAuth($redirectUrl = null, $refreshToken = null)
	{
		if($refreshToken != null)
		{
			self::getOAuthAcces(null, $refreshToken);
		}
		else
		{
			$params = array();
			$params['response_type'] = 'code';
			$params['client_id'] = '720048534826.apps.googleusercontent.com';
			$params['redirect_uri'] = $redirectUrl;
			$params['scope'] = self::CALENDAR_SCOPE;
			$params['access_type'] = 'offline';

			header("HTTP/1.1 307 Temporary Redirect");
			header('Location: ' . self::OAUTH_URL . '/auth?' . http_build_query($params));
		}

	}

	/**
	 * Set the calendar id to use
	 *
	 * @param	string $calendarId		The calendar ID
	 * @return void
	 */
	public function setCalendar($calendarId)
	{
		$this->calendar = $calendarId;
	}
}
/**
 * GoogleCalendarException
 *
 * @author Sam Tubbax <sam@sumocoders.be>
 */
class GoogleCalendarException extends Exception
{

}