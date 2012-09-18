<?php
if (!defined('FLUX_ROOT')) exit;

if (Flux::config('UseCaptcha') && Flux::config('EnableReCaptcha')) {
	require_once 'recaptcha/recaptchalib.php';
	$recaptcha = recaptcha_get_html(Flux::config('ReCaptchaPublicKey'));
}

$title = Flux::message('AccountCreateTitle');

$serverNames = $this->getServerNames();

if (count($_POST)) {
	require_once 'Flux/RegisterError.php';
	
	try {
		$server    = $params->get('server');
		$username  = $params->get('username');
		$password  = $params->get('password');
		$confirm   = $params->get('confirm_password');
		$email     = $params->get('email_address');
		$gender    = $params->get('gender');
		$birthDate = $params->get('birth_date');
		$code      = $params->get('security_code');
		
		if (!($server = Flux::getServerGroupByName($server))) {
			throw new Flux_RegisterError('Invalid server', Flux_RegisterError::INVALID_SERVER);
		}
		
		// Woohoo! Register ;)
		$result = $server->loginServer->register($username, $password, $confirm, $email, $gender, $birthDate, $code);

		if ($result) {
			if (Flux::config('RequireEmailConfirm')) {
				require_once 'Flux/Mailer.php';
				
				$user = $username;
				$code = md5(rand());
				$name = $session->loginAthenaGroup->serverName;
				$link = $this->url('account', 'confirm', array('_host' => true, 'code' => $code, 'user' => $username, 'login' => $name));
				$mail = new Flux_Mailer();
				$sent = $mail->send($email, 'Account Confirmation', 'confirm', array('AccountUsername' => $username, 'ConfirmationLink' => htmlspecialchars($link)));
				
				$createTable = Flux::config('FluxTables.AccountCreateTable');
				$bind = array($code);
				
				// Insert confirmation code.
				$sql  = "UPDATE {$server->loginDatabase}.{$createTable} SET ";
				$sql .= "confirm_code = ?, confirmed = 0 ";
				if ($expire=Flux::config('EmailConfirmExpire')) {
					$sql .= ", confirm_expire = ? ";
					$bind[] = date('Y-m-d H:i:s', time() + (60 * 60 * $expire));
				}
				
				$sql .= " WHERE account_id = ?";
				$bind[] = $result;
				
				$sth  = $server->connection->getStatement($sql);
				$sth->execute($bind);
				
				$session->loginServer->permanentlyBan(null, sprintf(Flux::message('AccountConfirmBan'), $code), $result);
				
				if ($sent) {
					$message  = Flux::message('AccountCreateEmailSent');
				}
				else {
					$message  = Flux::message('AccountCreateFailed');
				}
				
				$session->setMessageData($message);
				$this->redirect();
			}
			else {
				$session->login($server->serverName, $username, $password, false);
				$session->setMessageData(Flux::message('AccountCreated'));
				$this->redirect();
			}
		}
		else {
			exit('Uh oh, what happened?');
		}
	}
	catch (Flux_RegisterError $e) {
		switch ($e->getCode()) {
			case Flux_RegisterError::USERNAME_ALREADY_TAKEN:
				$errorMessage = Flux::message('UsernameAlreadyTaken');
				break;
			case Flux_RegisterError::USERNAME_TOO_SHORT:
				$errorMessage = Flux::message('UsernameTooShort');
				break;
			case Flux_RegisterError::USERNAME_TOO_LONG:
				$errorMessage = Flux::message('UsernameTooLong');
				break;
			case Flux_RegisterError::PASSWORD_TOO_SHORT:
				$errorMessage = Flux::message('PasswordTooShort');
				break;
			case Flux_RegisterError::PASSWORD_TOO_LONG:
				$errorMessage = Flux::message('PasswordTooLong');
				break;
			case Flux_RegisterError::PASSWORD_MISMATCH:
				$errorMessage = Flux::message('PasswordsDoNotMatch');
				break;
			case Flux_RegisterError::EMAIL_ADDRESS_IN_USE:
				$errorMessage = Flux::message('EmailAddressInUse');
				break;
			case Flux_RegisterError::INVALID_EMAIL_ADDRESS:
				$errorMessage = Flux::message('InvalidEmailAddress');
				break;
			case Flux_RegisterError::INVALID_GENDER:
				$errorMessage = Flux::message('InvalidGender');
				break;
			case Flux_RegisterError::INVALID_BIRTH_DATE:
				$errorMessage = Flux::message('InvalidBirthDate');
				break;
			case Flux_RegisterError::INVALID_SERVER:
				$errorMessage = Flux::message('InvalidServer');
				break;
			case Flux_RegisterError::INVALID_SECURITY_CODE:
				$errorMessage = Flux::message('InvalidSecurityCode');
				break;
			default:
				$errorMessage = Flux::message('CriticalRegisterError');
				break;
		}
	}
}
?>