public function store()
 {
  $rules = array(
            'vFirstname'        => 'required',
            'vLastname'        => 'required',
            'vUsername'        => 'required|unique:usersinfo',
            'vPhoneNo'        => 'required',
            'vPassword'        => 'required',
            'vCpassword'        => 'required|same:vPassword',
            'vEmail'        => 'required|email|unique:usersinfo',
            'eIsActive'   => 'required',
            'vProfilePicture'  => 'image',
        );

        $message = array(
         'vFirstname.required'  =>'First Name should not be blank.',
         'vLastname.required'  =>'Last Name should not be blank.',
         'vUsername.required'  =>'User Name should not be blank.',
         'vPhoneNo.required'   =>'Phone Number should not be blank.',
         'vPassword.required'  =>'Password should not be blank.',
         'vCpassword.required'  =>'Confirm Password should not be blank.',
         'vEmail.required'   =>'Email should not be blank.',
         'eIsActive.required'  =>'Please select the status.',
         'vProfilePicture.required' =>'Please select the correct image file',
         'vUsername.unique'   =>'Username has already been taken.',
         'vCpassword.same'   =>'Confirm password is not exactly as Password.',
         'vEmail.email'    =>'Email id is not a valid email id.',
         'vEmail.unique'    =>'This email Id has been already taken.',
        );
        $validator = Validator::make(Input::all(), $rules, $message);

        // process the login
        if ($validator->fails()) {
            return Redirect::to('usersinfo/create')
                ->withErrors($validator)
                ->withInput(Input::except('vPassword','vCpassword'));
        } else {
            // store
            $userinfo = new Usersinfo;
            $userinfo->vFirstname       = Input::get('vFirstname');
            $userinfo->vEmail        = Input::get('vEmail');
            $userinfo->eIsActive   = Input::get('eIsActive');
            $userinfo->vLastname       = Input::get('vLastname');
            $userinfo->vUsername       = Input::get('vUsername');
            $userinfo->vPhoneNo       = Input::get('vPhoneNo');
            $userinfo->vPassword   = Hash::make(Input::get('vPassword'));
            $userinfo->vCpassword   = Hash::make(Input::get('vCpassword'));
            $isFile      = Input::hasFile('vProfilePicture');
   //$userinfo->vProfilePicture  = Input::file('vProfilePicture');
            if($userinfo->save()){
             if($isFile){
              $file = Input::file('vProfilePicture');
              $destinationPath = 'uploads/'.$userinfo->iUserId.'/';
              if(!File::exists($destinationPath))
               File::makeDirectory($destinationPath,  $mode = 0777, $recursive = true);
              $filename = time().'-'.$file->getClientOriginalName();
              $file->move($destinationPath,$filename);
              $userinfo->vProfilePicture  = $filename;
              $userinfo = Usersinfo::find($userinfo->iUserId);
              $userinfo->save();
             }

             // redirect
             Session::flash('message', 'Successfully created User!');
             return Redirect::to('usersinfo');
            }
            else{
             Session::flash('error', 'Sorry,User can not be created!!');
             return Redirect::to('usersinfo');
            }
            
        }
 }
