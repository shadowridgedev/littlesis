<?php

class homeActions extends sfActions
{
  public function checkUser()
  {
    if (!$this->getUser()->isAuthenticated())
    {
      $this->forward('sfGuardAuth', 'signin');
    }  
  }


  public function clearUserCache()
  {
    LsCache::clearUserCacheById($this->getUser()->getGuardUser()->id);  
  }


  public function executeStart($request)
  {
    $this->redirect('@homepage');
  }


  public function executeWelcome($request)
  {  
    if ($this->getUser()->isAuthenticated())
    {
      if ($network = Doctrine::getTable("LsList")->find($this->getUser()->getProfile()->home_network_id))
      {
        $this->redirect("@localHome?name=" . $network["display_name"]);
      }
    }
  }
	

	public function executeIndex($request)
	{
    if ($this->getUser()->isAuthenticated())
    {
      if ($network = Doctrine::getTable("LsList")->find($this->getUser()->getProfile()->home_network_id))
      {
        $this->redirect("@localHome?name=" . $network["display_name"]);
      }
    }

    //get lists
    $listTable = Doctrine::getTable('LsList');

    $this->politician_list = $listTable->find(41);
    $this->fatcat_list = $listTable->find(88);
    $this->lobbyist_list = $listTable->find(102);
    $this->think_tank_list = $listTable->find(34);
    $this->philanthropy_list = $listTable->find(85);
    $this->pac_list = $listTable->find(114);


    //get stats
    $db = Doctrine_Manager::connection();
    
    $sql = 'SELECT COUNT(*) FROM entity WHERE primary_ext = ? AND is_deleted = 0';
    $stmt = $db->execute($sql, array('Person'));
    $this->person_num = $stmt->fetch(PDO::FETCH_COLUMN);

    $sql = 'SELECT COUNT(*) FROM entity WHERE primary_ext = ? AND is_deleted = 0';
    $stmt = $db->execute($sql, array('Org'));
    $this->org_num = $stmt->fetch(PDO::FETCH_COLUMN);

    $sql = 'SELECT COUNT(*) FROM relationship WHERE is_deleted = 0';
    $stmt = $db->execute($sql);
    $this->relationship_num = $stmt->fetch(PDO::FETCH_COLUMN);
    

    //get carousel entities
    $carousel_list_id = sfConfig::get('app_carousel_list_id');
    $sql = 'SELECT entity_id FROM ls_list_entity WHERE list_id = ?';
    $stmt = $db->execute($sql, array($carousel_list_id));
    $this->carousel_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    //shuffle($this->carousel_ids);

    //get top users    
    $this->analysts = LsDoctrineQuery::create()
      ->from('sfGuardUser u')
      ->leftJoin('u.Profile p')
      ->where('p.ranking_opt_out = 0')
      ->andWhere('u.id > 3')
      ->orderBy('p.score DESC')
      ->limit(5)
      ->execute();
  }
	
	public function executeJoin($request)
	{
    $userParams = $request->getParameter('user');
    
    $this->is_invited = false;
    $this->group = $request->getParameter('group');

    if ($this->group && $this->getUser()->isAuthenticated())
    {
      $this->redirect('@groupView?name=' . $this->group);
    }

    //if there's an invitation code supplied, it should match an invitation generated by an invite
    if ($code = $request->getParameter('code'))
    {
      $profile = Doctrine_Query::create()
        ->from('sfGuardUserProfile p')
        ->where('p.invitation_code = ?', $code)
        ->fetchOne();
        
      if ($profile)
      {
        $this->is_invited = true;
      }
    }
		
		if (!$this->is_invited)
		{
			$profile = new sfGuardUserProfile;
		}


    //if a network name is supplied
    if ($network_name = $request->getParameter('network'))
    {
      if ($network = LsListTable::getNetworkByDisplayName($network_name))
      {
        $profile->home_network_id = $network["id"];
      }
    }


    $this->user_form = new UserJoinForm($profile);
    $this->profile = $profile;

		
    //if form is posted, validate
		if ($request->isMethod('post'))
		{
      //bind request params to form
      $captcha = array(
        'recaptcha_challenge_field' => $request->getParameter('recaptcha_challenge_field'),
        'recaptcha_response_field'  => $request->getParameter('recaptcha_response_field'),
      );

      $userParams = array_merge($userParams, array('captcha' => $captcha));
		  $this->user_form->bind($userParams);

      //if public_name is valid, check that it's unique
      $errors = $this->user_form->getErrorSchema()->getErrors();

      if (!isset($errors['public_name']))
      {
        $q = LsDoctrineQuery::create()
          ->from('sfGuardUserProfile p')
          ->where('p.public_name LIKE ?', $userParams['public_name']);
  
        if (in_array($userParams['public_name'], sfGuardUserProfileTable::$prohibitedPublicNames) || $q->count())
        {
          $validatorSchema = $this->user_form->getValidatorSchema();
          $validatorSchema['public_name']->setMessage('invalid', 'Sorry, the public name you chose is already taken!');
          $this->user_form->getErrorSchema()->addError(new sfValidatorError($validatorSchema['public_name'], 'invalid'), 'public_name');
        }
      }



      //look for user with duplicate email
      $q = LsDoctrineQuery::create()
        ->from('sfGuardUserProfile p')
        //->where('p.email = ?', $userParams['email']);
        ->where('REPLACE(p.email, \'.\', \'\') = REPLACE(?, \'.\', \'\')', $userParams['email']);

      //if user was invited, the duplicate user shouldn't have the same code
      //if ($code)
      //{
      //  $q->addWhere('p.invitation_code <> ?', $code);
      //}
        
      if ($q->count())
      {
        $request->setError('email', 'There is already a user with that email');
      }

		   
		  //proceed if there are no errors
      if ($this->user_form->isValid() && !$request->hasErrors())
      {
        //if user is invited, consider user confirmed
        if ($this->is_invited)
        {
          $user = $profile->User;
          $user->is_active = true;

          $profile->invitation_code = null;
          $profile->is_visible = true;
          $profile->is_confirmed = true;
        }
        else
        {
          $user = new sfGuardUser;

          //auto-approve?
          $user->is_active = sfConfig::get('app_accounts_auto_approve') ? true : false;
        }



        $db = Doctrine_Manager::connection();
  
        try
        {
          $db->beginTransaction();

  
          //save submitted email as password
          $user->username = $userParams['email'];
          $user->algorithm = 'sha1';
          $user->setPassword($userParams['password1']);
          
          if (!$user->hasPermission('contributor'))
          {
            $user->addPermissionByName('contributor');
          }
          
          if (!$user->hasPermission('editor'))
          {
            $user->addPermissionByName('editor');
          }
          
          $user->save();


          //save submitted profile fields    
          $profile->user_id = $user->id;
          $profile->name_first = $userParams['name_first'];
          $profile->name_last = $userParams['name_last'];
          $profile->email = $userParams['email'];
          $profile->reason = $userParams['reason'];
          $profile->analyst_reason = $userParams['analyst_reason'];
          $profile->public_name = $userParams['public_name'];
          $profile->home_network_id = $userParams['home_network_id'];

          //if not invited, generate code for email confirmation
          if (!$this->is_invited)
          {
            $code = substr(sha1($profile->email . time()), 0, 20);
            $profile->confirmation_code = $code;
          }
                    
          $profile->save();

          
          //add user to group, if requested
          if ($this->group)
          {
            $db = Doctrine_Manager::connection();
            $sql = 'SELECT id FROM sf_guard_group WHERE name = ?';
            $stmt = $db->execute($sql, array($this->group));
            
            if ($groupId = $stmt->fetch(PDO::FETCH_COLUMN))
            {
              $ug = new sfGuardUserGroup;
              $ug->user_id = $user->id;
              $ug->group_id = $groupId;
              $ug->is_owner = 0;
              $ug->save();
            }
          }


          //send email to notify administrator of new account creation
          $mailBody = $this->getPartial('accountcreatenotify', array(
            'user' => $user,
            'analyst' => $userParams['analyst_reason'],
            'group' => $this->group,
          ));
    
          if ($this->is_invited)
          {
            $subject = 'LittleSis account invitation accepted by ' . 
              $userParams['name_first'] . ' ' . 
              $userParams['name_last'];            
          }
          else
          {
            $subject = 'LittleSis account ' . ($user->is_active ? 'created' : 'requested') . ' by ' . 
              $userParams['name_first'] . ' ' . 
              $userParams['name_last'];
          }
    
          $mailer = new Swift(new Swift_Connection_NativeMail());
          $message = new Swift_Message($subject, $mailBody, 'text/plain');
          $address = new Swift_Address(sfConfig::get('app_mail_join_sender_address'), sfConfig::get('app_mail_join_sender_name'));
    
          $mailer->send($message, sfConfig::get('app_mail_join_sender_address'), $address);
          $mailer->disconnect();
    

  
          //notify user that the account has been created/requested
          $subject = $user->is_active ? 'Welcome to LittleSis!' : 'Your request to become a LittleSis analyst';

          $mailBody = $this->getPartial(
            $user->is_active ? 'accountcreatereceipt' : 'accountrequestreceipt', 
            array(
              'user' => $user,
              'password' => $userParams['password1'],
              'is_invited' => $this->is_invited
            )
          );
    
          $mailer = new Swift(new Swift_Connection_NativeMail());
          $message = new Swift_Message('Welcome to LittleSis!', $mailBody, 'text/plain');
          $address = new Swift_Address(sfConfig::get('app_mail_join_sender_address'), sfConfig::get('app_mail_join_sender_name'));
    
          $mailer->send($message, $profile->email, $address);
          $mailer->disconnect();
  

          //if invited, sign in user and record login time
          if ($this->is_invited)
          {          
            // signin user
            $this->getUser()->setAttribute('user_id', $user->id, 'sfGuardSecurityUser');
            $this->getUser()->setAuthenticated(true);
            $this->getUser()->clearCredentials();
            $this->getUser()->addCredentials($user->getAllPermissionNames());
          
            // save last login
            $user->last_login = date('Y-m-d H:i:s');
            $user->save();
          }
  
  
          //commit changes
          $db->commit();            
        }
        catch (Exception $e)
        {
          $db->rollback();
          throw $e;
        }


        //redirect to requested or joined page
        if ($user->is_active)
        {
          $this->redirect('home/joined' . ($this->is_invited ? '?conf=1' : ''));
        }
        else
        {
          $this->redirect('home/requested');
        }
      }
		}		
	}

		
	public function executeRequested()
	{
	}
	
	
	public function executeJoined()
	{	
	}


  public function executeConfirmEmail($request)
  {
    if (!$code = $request->getParameter('code'))
    {
      $this->forward404();
    }
    
    $this->form = new LoginForm;
        
    if ($this->profile = Doctrine::getTable('sfGuardUserProfile')->findOneByConfirmationCode($code))
    {
      $this->profile->is_confirmed = true;
      $this->profile->confirmation_code = null;
      $this->profile->save();

      $this->getUser()->setFlash('profile', $this->profile);
      
      $this->redirect('home/confirmed');
    }
  }


  public function executeConfirmed($request)
  {
    if (!$this->profile = $this->getUser()->getFlash('profile'))
    {
      if ($this->getUser()->isAuthenticated())
      {
        $this->redirect('@homepage');
      }
      else
      {
        $this->forward404();
      }
    }
  }


  public function executeResetPassword($request)
  {
    $this->password_form = new ResetPasswordForm;
    $this->reset = false;
    
    
    if ($request->isMethod('post'))
    {
      $captcha = array(
        'recaptcha_challenge_field' => $request->getParameter('recaptcha_challenge_field'),
        'recaptcha_response_field'  => $request->getParameter('recaptcha_response_field'),
      );
      $params = array_merge($request->getParameter('reset_password'), array('captcha' => $captcha));
      
      $this->password_form->bind($params);
      
      if ($this->password_form->isValid())
      {
        $q = LsDoctrineQuery::create()
          ->from('sfGuardUserProfile p')
          ->leftJoin('p.User u')
          ->where('p.email = ? AND p.name_first = ? AND p.name_last = ?', array($params['username'], $params['name_first'], $params['name_last']));
        
        if (!$profile = $q->fetchOne())
        {
          $request->setError('', "Your email and name didn't match");
          
          return sfView::SUCCESS;
        }

        $tmpPass = substr(sha1(time() . $profile->User->password), 0, 10);
        $profile->User->setPassword($tmpPass);
        $profile->User->save();


        //notify user that we've received the account request
        $mailBody = $this->getPartial('passwordresetreceipt', array(
          'profile' => $profile,
          'password' => $tmpPass
        ));

        $mailer = new Swift(new Swift_Connection_NativeMail());
        $message = new Swift_Message('LittleSis password reset', $mailBody, 'text/plain');
        $address = new Swift_Address(sfConfig::get('app_mail_password_sender_address'), sfConfig::get('app_mail_password_sender_name'));
  
        $mailer->send($message, $profile->email, $address);
        $mailer->disconnect();
        
        $this->reset = true;
        $this->profile = $profile;
      }
    }
  }
  
  public function executeChangePassword($request)
  {
    $this->checkUser();
    $this->changed = false;
    $profile = $this->getUser()->getProfile();
    
    $this->password_form = new ChangePasswordForm;
    
    if ($request->isMethod('post'))
    {
      $params = $request->getParameter('change_password');
      
      $this->password_form->bind($params);
      if ($this->password_form->isValid())
      {
        $profile->User->setPassword($params['password1']);
        $profile->User->save();  
        $this->changed = true;
      }
    }
  }


  public function executeAccount($request)
  {
    $this->checkUser();
    
    $this->profile = $this->getUser()->getProfile();
    $this->stats = $this->profile->getShortSummary();
    $score = $this->profile->score;
    //$this->profile->refreshScore();
    if ($score !== null && $this->profile->score != $score)
    {
      $this->refreshed = true;
    }
    else
    {
      $this->refreshed = false;
    }
  }


	public function executeSettings()
	{
    $this->checkUser();
    
    $this->profile = $this->getUser()->getProfile();
	}

  
  public function executeEditProfile($request)
  {
    $this->checkUser();

    $this->profile = $this->getUser()->getProfile();
    $this->profile_form = new UserProfileForm($this->profile);
    
    if ($request->isMethod('post'))
    {
      $params = $request->getParameter('user_profile');
      $this->profile_form->bind($params);
      
      if ($this->profile_form->isValid())
      {
        $this->profile->show_full_name = $params['show_full_name'];
        $this->profile->bio = $params['bio'];
        $this->profile->save();

        $this->clearUserCache();

        $this->redirect($request->getParameter('referer', 'home/notes'));
      }      
    }
  }
  
  public function executeUploadImage($request)
  {
    $this->checkUser();
    $this->user = $this->getUser();
    $this->profile = $this->getUser()->getProfile();
    $this->upload_form = new ImageUploadForm();  
    
    $params = $request->getParameter('image'); 
    
    if ($request->isMethod('post'))
    {
      $db = Doctrine_Manager::connection();

      //$this->upload_form->bind($params, $request->getFiles('image'));
      
      try
      {
        $db->beginTransaction();


        $files = $request->getFiles('image');

        //set filename and path based on upload type
        if (isset($files['file']['size']) && $files['file']['size'])
        {
          $path = $request->getFilePath('image');
          $path = $path['file'];
          $originalFilename = $request->getFileName('image');
          $originalFilename = $originalFilename['file'];
        }
        else
        {
          $path = $params['url'];
          $pathParts = explode('?', basename($path));
          $originalFilename = $pathParts[0];
        }


        //if image files can't be created, assume remote url was bad
        if (!$filename = ImageTable::createFiles($path, $originalFilename))
        {
          $validatorSchema = $this->upload_form->getValidatorSchema();
          $this->upload_form->getErrorSchema()->addError(new sfValidatorError($validatorSchema['url'], 'invalid'));

          return sfView::SUCCESS;
        }
        
        $this->profile->filename = $filename;
        $this->profile->save();  
    
        //create reference
                
    
        //if featured, unfeature any other images
        if (isset($params['featured']) && $profileImage = $entity->getProfileImage())
        {
          $profileImage->is_featured = false;
          $profileImage->save();
        }
        
        $db->commit();
      }
      catch (Exception $e)
      {
        $db->rollback();
        throw $e;
      }

      $this->clearUserCache();

      $this->redirect($request->getParameter('referer', 'home/notes'));
    }
  }   
  
  public function executeRemoveImage($request)
  {
    $this->checkUser();
    $this->user = $this->getUser();
    $this->profile = $this->getUser()->getProfile();
    $this->profile->filename = null;
    $this->profile->save();

    $this->clearUserCache();

    $this->redirect($request->getReferer('home/notes'));
  } 


  public function executeEditSettings($request)
  {
    $this->checkUser();

    $user = $this->getUser()->getGuardUser();
    $profile = $user->getProfile();
    $this->profile = $profile;
    $this->settings_form = new UserSettingsForm($profile);
    
    if ($request->isMethod('post'))
    {
      $params = $request->getParameter('user_settings');
      $this->settings_form->bind($params);


      //if user submitted valid email address, make sure it isn't a duplicate
      $errors = $this->settings_form->getErrorSchema()->getErrors();

      if (!isset($errors['email']))
      {
        $q = LsDoctrineQuery::create()
          ->from('sfGuardUser u')
          ->where('u.id <> ? AND u.username LIKE ?', array($user->id, $params['email']));
        
        if ($q->count())
        {
          $validatorSchema = $this->settings_form->getValidatorSchema();
          $validatorSchema['email']->setMessage('invalid', "Sorry, there's already an account with that email address!");
          $this->settings_form->getErrorSchema()->addError(new sfValidatorError($validatorSchema['email'], 'invalid'), 'email');        
        }
      }

      
      if ($this->settings_form->isValid())
      {
        $user->username = $params['email'];
        $user->save();

        $profile->email = $params['email'];
        $profile->home_network_id = $params['home_network_id'];
        $profile->enable_announcements = isset($params['enable_announcements']) ? 1 : 0;
        $profile->enable_notes_notifications = isset($params['enable_notes_notifications']) ? 1 : 0;
        $profile->enable_recent_views = isset($params['enable_recent_views']) ? 1 : 0 ;
        $profile->enable_favorites = isset($params['enable_favorites']) ? 1 : 0;
        $profile->enable_pointers = isset($params['enable_pointers']) ? 1 : 0;
        $profile->enable_notes_list = isset($params['enable_notes_list']) ? 1 : 0;
        $profile->ranking_opt_out = isset($params['ranking_opt_out']) ? 1 : 0;
        $profile->watching_opt_out = isset($params['watching_opt_out']) ? 1 : 0;
        $profile->save();

        $this->clearUserCache();

        $this->redirect('home/settings');
      }      
    }  
  }  


  public function executeHidePointers($request)
  {
    $this->checkUser();
    
    $profile = $this->getUser()->getGuardUser()->getProfile();
    $profile->enable_pointers = false;
    $profile->save();
    
    $this->redirect($request->getReferer());
  }


  public function executeContact($request)
  {
    $useCaptcha = !$this->getUser()->isAuthenticated();

    $this->contact_form = new ContactForm(null, null, null, $useCaptcha);

    if ($request->getParameter('type') && $request->getParameter('type') == 'flag')
    {
      $this->flag_url = $request->getReferer();
    }
    
    if ($request->isMethod('post'))
    {
      if ($useCaptcha)
      {
        $captcha = array(
          'recaptcha_challenge_field' => $request->getParameter('recaptcha_challenge_field'),
          'recaptcha_response_field'  => $request->getParameter('recaptcha_response_field'),
        );

        $params = array_merge($request->getParameter('contact'), array('captcha' => $captcha));
      }
      else
      {
        $params = $request->getParameter('contact');   
      }
      
      $this->contact_form->bind($params, $request->getFiles('contact'));
      $this->flag_url = $request->getParameter('flag_url');

      //require name and email if not authenticated
      if (!$this->getUser()->isAuthenticated())
      {
        if (!$params['name'] || !$params['email'])
        {
          if (!$params['name'])
          {
            $request->setError('name', 'Name is required');
          }
          
          if (!$params['email'])
          {
            $request->setError('email', 'Email is required');
          }
          
          return sfView::SUCCESS;
        }
        
        $name = $params['name'];
        $email = $params['email'];
      }
      else
      {
        $name = $this->getUser()->getGuardUser()->getProfile()->getFullName();
        $email = $this->getUser()->getGuardUser()->username;
      }
                  
      if ($this->contact_form->isValid())
      {
        //send message to admin
        $mailBody = $this->getPartial('contactformnotify', array(
          'name' => $name,
          'email' => $email,
          'subject' => $params['subject'],
          'message' => $request->getParameter('flag_url') ? $params['message'] . "\n\nReferrer:   " . $request->getParameter('flag_url') : $params['message']
        ));

        $mailer = new Swift(new Swift_Connection_NativeMail());
        $message = new Swift_Message($params['subject'], $mailBody, 'text/plain');
        $address = new Swift_Address($email, $name);

        if ($request->hasFile('contact')) // && strlen($request->getFilePath('contact')))
        {
          $paths = $request->getFilePath('contact');
          $filenames = $request->getFileName('contact');
          $types = $request->getFileType('contact');

          if ($paths['file'] != '')
          {
            $message->attach(new Swift_Message_Part($mailBody));
            $message->attach(new Swift_Message_Attachment(
              new Swift_File($paths['file']),
              $filenames['file'], 
              $types['file']
            ));
          }
        }
        else
        {
          var_dump($request->getFiles('contact')); die;
        }
  
        $mailer->send($message, sfConfig::get('app_mail_contact_recipient_address'), $address);
        $mailer->disconnect();
        
        $this->sent = true;
      }
    }
  }

  
  public function executeModifications($request)
  {
    $this->checkUser($request);

    $page = $request->getParameter('page', 1);
    $num = $request->getParameter('num', 20);

    $this->profile = $this->getUser()->getGuardUser()->getProfile();
    $this->modification_pager =  new LsDoctrinePager($this->profile->User->getModificationsQuery()->setHydrationMode(Doctrine::HYDRATE_ARRAY), $page, $num);
  }

  
  public function executeInvite($request)
  {
    $email = $request->getParameter('email');
    $error = true;
    if (preg_match('/^([^@\s]+)@((?:[-a-z0-9]+\.)+[a-z]{2,})$/i',$email))
    {
      $error = false;
    }
    
    if (!$error)
    {
      $name = $this->getUser()->getGuardUser()->getProfile()->getFullName();
      
      $mailBody = $this->getPartial('inviteEmail', array(
              'name' => $name
            ));
  
      $mailer = new Swift(new Swift_Connection_NativeMail());
      $message = new Swift_Message($name . ' has invited you to join LittleSis', $mailBody);
      $address = new Swift_Address('do-not-reply@littlesis.org', 'LittleSis');
      
      $mailer->send($message, $email, $address);
      $mailer->disconnect();
    }      
    
    return $this->renderPartial('home/inviteResult',array('email' => $email, 'error' => $error));
  }


  public function executeRefreshScore($request)
  {
    if (!$this->getUser()->hasCredential('contributor'))
    {
      $this->forward('error/invalid');
    }
    
    $this->getUser()->getProfile()->refreshScore();
        
    $this->redirect($request->getReferer('@homepage'));
  }


  public function executeNotes($request)
  {
    $this->checkUser();
    $this->profile = $this->getUser()->getProfile();
    $this->note_form = new NoteForm;
    $this->unread_notes = $this->profile->unread_notes;
    $this->profile->unread_notes = 0;
    $this->profile->save();


    //get network options
    $networkIds = array_unique(array(sfGuardUserTable::getHomeNetworkId(), LsListTable::US_NETWORK_ID));
    $networkIds = array_unique(array_merge($networkIds, $request->getParameter('network_ids', array())));

    if (count($networkIds) > 1)
    {
      $this->networks = LsDoctrineQuery::create()
        ->from('LsList l')
        ->whereIn('l.id', $networkIds)
        ->fetchArray();    
    }
        

    if ($request->isMethod('post'))
    {
      $params = $request->getParameter('note');
      $this->note_form->bind($params);
      
      if ($this->note_form->isValid())
      {
        $db = Doctrine_Manager::connection();

        try
        {
          $db->beginTransaction();
  
          //associate note with specified networks, or else the user's home network
          $networkIds = $request->getParameter('network_ids', array(sfGuardUserTable::getHomeNetworkId()));

          $note = new Note;
          $note->user_id = $this->getUser()->getGuardUser()->id;
          $note->encodeBody($params['body']);
          $note->is_private = isset($params['is_private']) ? true : false;
          $note->network_ids = NoteTable::serialize($networkIds);
          $note->save();
  
          //if there are alerted users, add notification emails to email queue
          foreach (NoteTable::unserialize($note->alerted_user_names) as $public_name)
          {
            if ($profile = Doctrine::getTable('sfGuardUserProfile')->findOneByPublicName($public_name))
            {
              $public_name = $this->getUser()->getGuardUser()->getProfile()->public_name;
              $profile->unread_notes++;
              $profile->save();
              
              $email = new ScheduledEmail;
              $email->from_name = sfConfig::get('app_mail_notes_sender_name');
              $email->from_email = sfConfig::get('app_mail_notes_sender_address');
              $email->to_name = $profile->getName();
              $email->to_email = $profile->email;
              $email->subject = $public_name . ' has written you a note on LittleSis';
              $email->body_text = $this->getPartial('notenotify', array(
                'note_author' => $public_name,
                'note_author_id' => $note->user_id,
                'note_body' => NoteTable::prepareBodyForEmail($note->body),
                'note_is_private' => $note->is_private 
              ));            
              $email->body_html = nl2br($this->getPartial('notenotify', array(
                'note_author' => $public_name,
                'note_author_id' => $note->user_id,
                'note_body' => NoteTable::prepareBodyForEmail($note->body, true),
                'note_is_private' => $note->is_private 
              )));
              $email->save();
            }
          }
          
          $db->commit();          
        }
        catch (Exception $e)
        {
          $db->rollback();
          throw $e;
        }

        $this->redirect('home/notes');
      }
    }

    $page = $request->getParameter('page', 1);
    $num = $request->getParameter('num', 20);
    $withReplies = $request->getParameter('replies', 1);
    
    //get notes to/from the current user using sphinx
    $s = new LsSphinxClient($page, $num);
    $currentUserId = sfGuardUserTable::getCurrentUserId();
    
    if ($withReplies)
    {
      $s->setFilter('visible_to_user_ids', array($currentUserId));
    }
    else
    {
      $s->setFilter('user_id', array($currentUserId));
    }
    
    $this->note_pager = NoteTable::getSphinxPager($s, null, Doctrine::HYDRATE_ARRAY);

    //execute pager to get most recently indexed note
    $notes = $this->note_pager->execute();
    $lastNoteId = count($notes) ? $notes[0]['id'] : 1;

    //get new notes that may not yet be indexed
    $this->new_notes = LsDoctrineQuery::create()
      ->from('Note n')
      ->leftJoin('n.User u')
      ->leftJoin('u.Profile p')
      ->where('n.user_id = ?', $this->getUser()->getGuardUser()->id)
      ->andWhere('n.id > ?', $lastNoteId)
      ->orderBy('n.id DESC')
      ->setHydrationMode(Doctrine::HYDRATE_ARRAY)
      ->execute();
  }
  
  
  public function executeGroups($request)
  {
    $this->checkUser($request);
    $this->profile = $this->getUser()->getGuardUser()->getProfile();

    $db = Doctrine_Manager::connection();

    //first get list of groups this user belongs to
    $sql = 'SELECT group_id FROM sf_guard_user_group WHERE user_id = ?';
    $stmt = $db->execute($sql, array($this->getUser()->getGuardUser()->id));
    $groupIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($groupIds))
    {
      $sql = 'SELECT g.*, COUNT(ug.user_id) users FROM sf_guard_group g ' . 
             'LEFT JOIN sf_guard_user_group ug ON (ug.group_id = g.id) ' . 
             'WHERE g.id IN (' . implode(', ', $groupIds) . ') AND g.is_working = 1 GROUP BY g.id ORDER BY users DESC';
      $stmt = $db->execute($sql);
      
      $this->groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    else
    {
      $this->groups = array();
    }
  }


	public function executePurpose()
	{
    $this->redirect('@about');
	}


	public function executeFeatures()
	{
    $db = Doctrine_Manager::connection();

    //get numbers of people, orgs, and relationships
    $sql = 'SELECT COUNT(*) FROM entity e WHERE e.primary_ext = ? AND e.is_deleted = 0;';
    $stmt = $db->execute($sql, array('Person'));
    $this->person_num = $stmt->fetch(PDO::FETCH_COLUMN);

    $stmt = $db->execute($sql, array('Org'));
    $this->org_num = $stmt->fetch(PDO::FETCH_COLUMN);

    $sql = 'SELECT COUNT(*) FROM relationship r WHERE r.is_deleted = 0;';
    $stmt = $db->execute($sql);
    $this->relationship_num = $stmt->fetch(PDO::FETCH_COLUMN);

    //get walmart object for analyst note example
    $this->walmart = Doctrine::getTable('Entity')->find(1);
	}


	public function executeTeam()
	{	
	}
	
	public function executeJobs()
	{
    $this->redirect('@homepage');
	}
	
	public function executeFunding()
	{
	  $this->redirect('@about');
	}
	
	public function executeGuide()
	{	
	}
	
	public function executeFaq()
	{
	}	
	
	public function executeHowto()
	{
	}
	
	public function executeDisclaimer()
	{	
	}
	
  public function executePress()
  {  
  }
  
  public function executeAbout()
  {
  }
  
  public function executeVideos()
	{
	}
	
	public function executeChat($request)
	{
    $this->checkUser();
    $this->room = $request->getParameter('room', 1);

    if ($this->room < 1 || $this->room > 20)
    {
      $this->room = 1;
    }
    
	  //log chat user
    $db = Doctrine_Manager::connection();
    $sql = 'REPLACE INTO chat_user (user_id, room, updated_at) VALUES (?, ?, ?)';  //use REPLACE in order to update timestamp
    $stmt = $db->execute($sql, array(
      $this->getUser()->getGuardUser()->id,
      $this->room,
      LsDate::getCurrentDateTime()
    ));
	  
	  //get other chat users
    $q = LsDoctrineQuery::create()
      ->from('sfGuardUser u')
      ->leftJoin('u.Profile p')
      ->leftJoin('u.ChatUser cu')
      ->where('cu.room = ?', $this->room)
      ->andWhere('cu.updated_at > ?', date('Y-m-d H:i:s', strtotime('5 minutes ago')))
      ->andWhere('u.id <> ?', $this->getUser()->getGuardUser()->id)
      ->setHydrationMode(Doctrine::HYDRATE_ARRAY);
      
    $this->users = $q->execute();	  
	}


  public function executeMakeHomeNetwork($request)
  {
    if (!$request->isMethod('post'))
    {
      $this->forward('error', 'invalid');
    }

    $this->checkUser();

    if ($network = Doctrine::getTable('LsList')->find($request->getParameter('id')))
    {
      $profile = $this->getUser()->getProfile();
      $profile->home_network_id = $network["id"];
      $profile->save();
      $this->redirect("@localHome?name=" . $network["display_name"]);
    }
    else
    {
      $this->forward404();
    }
  }
  
  
  public function executeLocal($request)
  {
    
  }
  
  
  public function executeData($reuqest)
  {

  }
}