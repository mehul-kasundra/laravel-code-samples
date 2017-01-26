<?php
namespace App\Http\Controllers\Frontend; //admin add

use App\Http\Controllers\Controller; // using controller class
use Illuminate\Support\Facades\Input; // using controller class
use Illuminate\Support\Facades\Validator; // using controller class
use Illuminate\Support\Facades\Auth; // using controller class
use Illuminate\Support\Facades\Redirect; // using controller class
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use App\User;
use App\Http\Models\Profile;
use App\Http\Models\Country;
use View;
use Image;
use DateTime;
use Mail;
class HomepageController extends Controller {
	public function index() {
		return view('frontend.homepage');
	}
	public function signup() {
		 if(Auth::check() && Auth::user()->role != 'admin')
		 {
			 return Redirect::to('userprofile');
			
	   	}
	   	else
	   	{
			return view('frontend.signup');
		}
		
	}
	public function signin() {
	
		 if(Auth::check() && Auth::user()->role != 'admin')
		 {
			 
			 return Redirect::to('userprofile');
			
		 }
		 else
		 {
			 return view('frontend.signin');
		 }
	}

	public function dosignin()
	{
		
		$data = Input::all();
		// Applying validation rules.
		$rules = array(
			'email' => 'required|email',
			'password' => 'required|min:6',
		);
		$validator = Validator::make($data, $rules);
		if ($validator->fails()){
		  // If validation falis redirect back to login.
		  return Redirect::to('signin')->withInput(Input::except('password'))->withErrors($validator);
		}
		else {
			$remember_me = Input::get('remember_me') ? true : false; 
		  $userdata = array(
				'email' => Input::get('email'),
				'password' => Input::get('password'),
				'role'=>NULL,
			  );
			   
		  // doing login.
		  if (Auth::validate($userdata)) {
			if (Auth::attempt($userdata, $remember_me)) {
				return Redirect::intended('userprofile');
			}
		  } 
		  else {
			// if any error send back with message.
			Session::flash('error', 'Invalid Username or Password!!'); 
			return Redirect::to('signin');
		  }
		}
	}
	public function doSignUp()
	{
			$data = Input::all();
			$rules = array(
				'email' => 'required|email|unique:users',
				'password' => 'required|confirmed|min:6',
			);
			$validator = Validator::make($data, $rules);
			if ($validator->fails()){
				// If validation falis redirect back to login.
				return Redirect::to('signup')->withInput()->withErrors($validator);
			}
			else
			{
					Session::forget('email');
					Session::forget('password');
					Session::forget('firstname');
					Session::forget('lastname');
					Session::forget('profile_pic');
					
					session(['email' => $data['email']]);
					session(['password' =>bcrypt($data['password'])]);
					
				return Redirect::to('profile');
				
			}
			
			
	}
	public function api_login(){
		$data = Input::all();
		// Applying validation rules.
		$rules = array(
			'email' => 'required|email',
			'password' => 'required|min:6',
		);
		$validator = Validator::make($data, $rules);
		$response=array();
		$apiData=array();
		if ($validator->fails()){
		  // If validation falis redirect back to login.
		  $errors=array();
		  foreach($validator->errors()->all() as $error){
				$errors[]=$error;
			}
			$response['error']=$errors;
		}else {
		  $userdata = array(
				'email' => Input::get('email'),
				'password' => Input::get('password'),
				'role'=>NULL,
			  );
			   
		  // doing login.
		  if (Auth::validate($userdata)) {
			$apiData['email']=$userdata['email'];
			$apiData['role']=$userdata['role'];
			$response['data']=$apiData;
			$response['message']='User Login Successfully';
		  } 
		  else {
			// if any error send back with message.
			$response['error']='Problem in User Login';
		  }
		}
		return $response;
	}
	
	public function api_signup_step1(){
		$data=Input::all();//get all input params
		// Applying validation rules.
		$rules = array(
			'email' => 'required|email|unique:users',
			'password' => 'required|confirmed|min:6',
		);
		$validator = Validator::make($data, $rules);
		$response=array();
		$apiData=array();
		if ($validator->fails()){
			if($validator->errors()->all()){
				$errors=array();
				foreach($validator->errors()->all() as $error){
					$errors[]=$error;
				}
			}
		  $response['error']=$errors;
		}else{
			$dt = new DateTime;
			$currentDate= $dt->format('Y-m-d H:i:s');
			$user=User::create([
				'email' => $data['email'],
				'password' => bcrypt($data['password']),
				'last_active'=>$currentDate,
				'status'=>1,
			]);
			if($user->id){
				$apiData['id']=$user->id;
				$apiData['email']=$data['email'];
				$response['data']=$apiData;
				$response['message']='Registeration Step1 is Completed';
			}
			
		}
		return $response;
	}
		
	
	public function api_signup_step2(){
		
		$dt = new DateTime;
		$currentDate= $dt->format('Y-m-d H:i:s');
		$data = Input::all();
		$rules = array(
			'dob' => 'required',
			'fname' => 'required',
			'lname' => 'required',
			'country' => 'required',
		);
		$validator = Validator::make($data, $rules);
		$response=array();
		if ($validator->fails()){
			// If validation falis redirect back to login.
			if($validator->errors()->all()){
				$errors=array();
				foreach($validator->errors()->all() as $error){
					$errors[]=$error;
				}
			}
		  $response['error']=$errors;
		}else{
			$user_id=$data['user_id'];
			$user=User::where('id', '=', $user_id)->first();
			if($user){
				$userId = $user->id;
				$profile_pic='';
				if($data['profile_image']){
					$destinationPath = public_path() .'/uploads/Profile/';
					$filename = time().'profile_image';
					$profile_pic = $filename;
					$baseimage=explode(",",$data['profile_image']);
					echo $baseimage1=explode("/",$baseimage[0]);die;
				}
				$profile = new Profile;
				$profile->user_id = $userId;
				$profile->firstname = $data['fname'];
				$profile->profile_pic = $filename;
				$profile->lastname = $data['lname'];
				$profile->country = $data['country'];
				$profile->save();
				
			}
		}
		return $response; 
		
	}
	
	public function profile()
	{
		 $lang=getselectedlang();
		$class='cropit'.$lang;
		 if(Session::has('email'))
		 {
			   $countries = Country::all();
			   $countryarr=array();
			   foreach ($countries as $countryname) {
				   $countryarr[$countryname->id]=$countryname->name;
				}
				
					
				
				$countriesData=array('countries'=>$countryarr,'class'=>$class);
			return View::make('frontend/profile')->with($countriesData);
		}
		else
		{
			return Redirect::to('signup');
		}
			
	}
	public function doProfile()
	{
		$dt = new DateTime;
		$currentDate= $dt->format('Y-m-d H:i:s');
		$data = Input::all();
		$rules = array(
			'dob' => 'required',
			'fname' => 'required',
			'lname' => 'required',
			'country' => 'required',
		);
		if(!Session::has('profile_pic'))
		{
			$rules['image']     = 'required|mimes:png,jpg,jpeg';
		}
		$validator = Validator::make($data, $rules);
		if ($validator->fails()){
			// If validation falis redirect back to login.
			return Redirect::to('profile')->withInput()->withErrors($validator);
		}
		else
		{
			 if(Session::has('email'))
			 {
				
				$user=User::create([
					'email' => Session::get('email'),
					'password' => Session::get('password'),
					'last_active'=>$currentDate,
					 'status'=>1,
				]);
				$userId = $user->id;
				/*Session::forget('email');
				Session::forget('password');
				Session::forget('firstname');
				Session::forget('lastname');
				Session::forget('profile_pic');*/
			
				/**upload profile Pic **/
				
					$profile_pic='';
					if(Input::file('image'))
					{
						$file = Input::file('image');
						$destinationPath = public_path() .'/uploads/Profile/';
						$filename = time().$file->getClientOriginalName();
						$profile_pic = $filename;
						$baseimage=explode(",",$data['hiddenprofile']);
						$imageData = base64_decode($baseimage[1]);
						$source = imagecreatefromstring($imageData);
						//$rotate = imagerotate($source, 0, 0); // if want to rotate the image
						$extenionname=$file->getClientOriginalExtension();
						if($extenionname=="png")
						{
							$imageSave = imagepng($source,$destinationPath.$filename,9);
						}
						else
						{
							$imageSave = imagejpeg($source,$destinationPath.$filename,100);
						}
						imagedestroy($source);
						/*if (Input::file('image')->isValid()) { 
							 $file = Input::file('image');
							
							$destinationPath = public_path() .'/uploads/Profile/';
							
							$filename = time().$file->getClientOriginalName();
							//Input::file('image')->move($destinationPath, $filename);
							Image::make($file)->resize(300,300)->save(public_path('uploads/Profile/' . $filename));
							$profile_pic = $filename ;
						}*/

					}
					else
					{
						$path = $data['profile-img'];
						$path=str_replace("?","",$path);
						$filename = time().basename($path);
						Image::make($path)->resize(300,300)->save(public_path('uploads/Profile/' . $filename));
						$profile_pic=$filename;
					}
					
			
					/** upload profile pif **/
					
					Profile::create([
						'user_id' =>  $userId ,
						'firstname' =>  $data['fname'],
						'lastname' =>   $data['lname'],
						'profile_pic'=> $profile_pic,
						'dob' => $data['dob'],
						'country' => $data['country'],
					]);	
					
					$data['email']=Session::get('email');
					
					Mail::send('emails.welcome', $data, function($message) use ($data)
					{
							$message->from('no-reply@site.com', "Same Day Twins");
							$message->subject("Registration");
							$message->to($data['email']);
					});
					
					Auth::login($user, true);
					return Redirect::intended('userprofile');
						
					 
					
			 }
			 else
			 {
					return Redirect::to('signup');
			 }
				
				
				
		}
	}
	public function logout(){
		$auth=Auth::user();
		$userid=$auth->id;
		$dt = new DateTime;
		$currentDate= $dt->format('Y-m-d H:i:s');
		$update=array('status'=>'0','last_active'=>$currentDate);
		User::where('id', '=', $userid)->update($update);
		Auth::logout();
		return Redirect::to('signin');
	}
	
	public function profilePictureUpload()
	{ 
		//$baseUrl=url('/');
		if (Input::file('file')->isValid()) { 
									 $file = Input::file('file');
									
									$destinationPath = public_path() .'/uploads/Temp/';
									
									$filename = time().$file->getClientOriginalName();
									//Input::file('image')->move($destinationPath, $filename);
									Image::make($file)->save(public_path('uploads/Temp/' . $filename));
									$profile_pic =asset('uploads/Temp')."/".$filename ;
								}
		return $profile_pic;
	}
	
	
}
