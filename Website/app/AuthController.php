<?php

/*
 * MovieContentFilter (http://www.moviecontentfilter.com/)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the GNU AGPL v3 (https://www.gnu.org/licenses/agpl-3.0.txt)
 */

namespace App;

use Delight\Auth\EmailNotVerifiedException;
use Delight\Auth\InvalidEmailException;
use Delight\Auth\InvalidPasswordException;
use Delight\Auth\TooManyRequestsException;
use Delight\Auth\UserAlreadyExistsException;
use Delight\Foundation\App;

class AuthController extends Controller {

	const MIN_PASSWORT_LENGTH = 8;

	public static function showSignUp(App $app) {
		echo $app->view('sign-up.html');
	}

	private static function sendEmail(App $app, $subject, $template, $toAddress, $toName = null, $params = null) {
		// since we're sending an email, this request *may* take a bit longer in some rare cases
		set_time_limit(60);

		// never stop execution before the email has been sent just because the client disconnected
		ignore_user_abort(true);

		// if no parameters have been provided
		if ($params === null) {
			// initialize an empty array
			$params = [];
		}

		// add the general parameters that are passed to all email templates
		$params['projectName'] = $_ENV['COM_MOVIECONTENTFILTER_PROJECT_NAME'];
		$params['projectUrl'] = parse_url($_ENV['APP_PUBLIC_URL'], PHP_URL_HOST);
		$params['projectEmail'] = $_ENV['COM_MOVIECONTENTFILTER_MAIL_REPLY_TO'];
		$params['projectPostal'] = $_ENV['COM_MOVIECONTENTFILTER_PROJECT_POSTAL'];
		$params['address'] = $toAddress;
		$params['name'] = $toName;

		// combine the template and the parameters into the complete body text
		$body = $app->view($template, $params);

		// create a new message object
		/** @var \Swift_Mime_Message $obj */
		$obj = $app->mail()->createMessage();

		// configure the message
		$obj->setSubject($subject);
		$obj->setFrom(
			[ $_ENV['COM_MOVIECONTENTFILTER_MAIL_FROM'] => $_ENV['COM_MOVIECONTENTFILTER_PROJECT_NAME'] ]
		);
		if (empty($toName)) {
			$obj->setTo(
				[ $toAddress ]
			);
		}
		else {
			$obj->setTo(
				[ $toAddress => $toName ]
			);
		}
		$obj->setBody($body);

		// send the message and return whether this operation succeeded
		return $app->mail()->send($obj);
	}

	public static function saveSignUp(App $app) {
		$email = $app->input()->post('email', TYPE_EMAIL);
		$password1 = $app->input()->post('password-1');
		$password2 = $app->input()->post('password-2');
		$displayName = $app->input()->post('display-name');

		if (!empty($email)) {
			if (!empty($password1) && strlen($password1) >= self::MIN_PASSWORT_LENGTH) {
				if ($password1 === $password2) {
					try {
						$app->auth()->register($email, $password1, $displayName);

						$app->flash()->success('Thanks for signing up! You can now sign in with your email address and password.');
						$app->redirect('/');
					}
					catch (InvalidEmailException $e) {
						$app->flash()->warning('Please check your email address and try again. Thank you!');
						$app->redirect('/sign-up');
					}
					catch (InvalidPasswordException $e) {
						$app->flash()->warning('Please check the requirements for your password and try again. Thank you!');
						$app->redirect('/sign-up');
					}
					catch (UserAlreadyExistsException $e) {
						$app->flash()->warning('An account with that email address does already exist. Do you want to sign in instead?');
						$app->redirect('/sign-up');
					}
					catch (TooManyRequestsException $e) {
						$app->flash()->warning('Please try again later!');
						$app->redirect('/sign-up');
					}
				}
				else {
					$app->flash()->warning('The two passwords didn\'t match. Please try again. Thank you!');
					$app->redirect('/sign-up');
				}
			}
			else {
				$app->flash()->warning('Please check the requirements for your password and try again. Thank you!');
				$app->redirect('/sign-up');
			}
		}
		else {
			$app->flash()->warning('Please check your email address and try again. Thank you!');
			$app->redirect('/sign-up');
		}
	}

	public static function processLogin(App $app) {
		$email = $app->input()->post('email', TYPE_EMAIL);
		$password = $app->input()->post('password');
		$continueToPath = $app->input()->post('continue');

		try {
			$app->auth()->login($email, $password, true);

			// if a desired target path to redirect to has been specified
			if (!empty($continueToPath)) {
				// if the target path is a local path
				if (s($continueToPath)->matches('/^\\/[^\\/]/')) {
					// redirect to the requested path
					$app->redirect($continueToPath);
					exit;
				}
			}

			// otherwise redirect to the root path
			$app->redirect('/');
		}
		catch (InvalidEmailException $e) {
			$app->flash()->warning('Please check your email address and password and try again!');
			$app->redirect('/');
		}
		catch (InvalidPasswordException $e) {
			$app->flash()->warning('Please check your email address and password and try again!');
			$app->redirect('/');
		}
		catch (EmailNotVerifiedException $e) {
			$app->flash()->warning('Please verify your email address before being able to sign in. Thank you!' . "\n" . 'You should have received an email containing an activation link that you should open.');
			$app->redirect('/');
		}
		catch (TooManyRequestsException $e) {
			$app->flash()->warning('Please try again later!');
			$app->redirect('/');
		}
	}

	public static function logout(App $app) {
		$app->auth()->logout();

		$app->flash()->success('You have been successfully logged out. See you next time!');
		$app->redirect('/');
	}

}
