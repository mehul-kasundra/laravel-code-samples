<?php

namespace App\Http\Controllers;

use App\Role;
use App\role_user;
use App\User;
use App\Group;
use App\Category;
use App\GroupEvent;
use App\CmsTemplate;
use App\CmsTemplateImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use File;
use Illuminate\Support\Facades\DB;
use Hash;
use Maatwebsite\Excel\Facades\Excel;
use Validator;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\GroupImage;
class FindGroupController extends Controller
{
    public function __construct()
    {
        // Apply the jwt.auth middleware to all methods in this controller
        // except for the authenticate method. We don't want to prevent
        // the user from retrieving their token if they don't already have it
        //$this->middleware('jwt.auth', ['except' => ['authenticate','uploadimage','deleteUpload']]);
    }
    

    public function exportFile(Request $request) {
//        dd($request['selection']);
        ### $request['export_type'] is export mode  "EXCEL or CSV"
        ### Check export CSV permission
//        if ($request['export_type'] == 'csv' && !Auth::user()->can('export_csv'))
//            return 'You not have this permission';
//
//        ### Check export EXCEL permission
//        if ($request['export_type'] == 'xls' && !Auth::user()->can('export_xls'))
//            return 'You not have this permission';



        ### record_type 1 equal whole records and 2 equals selected records
//        if ($request['record_type'] == 1) {
//            $users = User::all();
//        } else if ($request['record_type'] == 2) {
////            return $request['selection'];
////            $temp = explode(",", $request['selection']);
//            //        foreach($temp as $val) {
//            //             $users = User::find($val);
////            }
//            $users = User::findMany($request['selection']);
//        }

        ###
        if ($request['export_type'] == 'pdf') { //export PDF
            $html = '<h1 style="text-align: center">RSVP List</h1>';
            $html .= '<style> table, th, td {text-align: center;} th, td {padding: 5px;} th {color: #43A047;border-color: black;background-color: #C5E1A5} </style> <table border="2" style="width:100%;"> <tr> <th>Name</th></tr>';
            foreach ($request['selection']as $user) {
                $user_name = json_decode($user);
                $name = $user_name->name;
                //$user_name = $user['name'];
                $html .="<tr> <td>$name</td></tr>";
            }
            $html .= '</table>';
            $pdf = App::make('dompdf.wrapper');
            $headers = array(
                'Content-Type: application/pdf',
            );
            $pdf->loadHTML($html);
            return $pdf->download('permission.pdf', $headers);
        } else {
            Excel::create('user', function ($excel) use ($users) {
                $excel->sheet('Sheet 1', function ($sheet) use ($users) {
                    $sheet->fromArray($users);
                });
            })->download($request['export_type']);
        }
    }
        

    /*
     *  Search Method
     */
     public function search(Request $request)
    {
        //dd($request);
        $per_page = \Request::get('per_page') ?: 10;
        ### search
        if ($request['query']) {
            $Group = Group::search($request['query'], null, false)->get();
            $page = $request->has('page') ? $request->page - 1 : 0;
            $total = $Group->count();
            $Group = $Group->slice($page * $per_page, $per_page);
            $Group = new \Illuminate\Pagination\LengthAwarePaginator($Group, $total, $per_page);
            return  $Group;
        }
        return 'not found';
    }



    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $per_page = \Request::get('per_page') ?: 10;
                
            
            $groupdata=Group::orderBy('created_at', 'asec')->paginate($per_page);
            
            //dd($groupdata);
            return $groupdata;
        
    }

    
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //dd($id);
        $Group = Group::find($id);
        if(!$Group)
           return response()->json(['error' => 'not found item'], 404);
        
        if(!empty($Group->GroupInterest)){
            
            $group_interest=DB::table('categories')
                            ->join('group_interests', 'group_interests.category_id', '=', 'categories.id')
                            ->where('group_interests.group_id', '=', $id)
                            ->select('categories.category_name')
                            ->first();
        
            $Group->category_name=$group_interest->category_name;
        }else{
            $Group->category_name="N/A";
        }
        
//        dd($group_interest);
        $group_member = $Group->GroupMember;
        
        $count_group_member = count($group_member);
        $user_id=array();
        foreach($group_member as $single_member){
            $user_id[] = $single_member->user_id;
  
        }
		
        $user= implode(",",$user_id);
        if(!empty($user)){
            $member_details =  DB::table('users')->select('id','name', 'email')->whereIn('id',$user_id)->get();
        }else{
            $member_details = "";
        }
                //dd($member_details);
        $Group->members = $member_details;
        
        $Group->members_count = $count_group_member;
        $Group->events=$Group->GroupEvent;
//        $Group->categories=category::all();
        $Group->ImageGallery=GroupImage::where('group_id',$id)->get();    
        
        if($Group)
            return $Group;
        else
            return response()->json(['error' => 'not found item'], 404);
    }

    
    public function show_event(Request $request)
    {
        $id=$request->all();
        
            $Event = GroupEvent::find($id);
            return $Event;
        
    }
    
    
    public function member_list(Request $request){
        
        if(!empty($request)){
            $id = $request['id'];
            //$group_member = $id->GroupMember;
            $group_member = DB::table('group_members')->where('group_id', $id)->where('status',2)->get();
            $member=array();
            $num=0;
            foreach($group_member as $single_user){
                
                $user_id = $single_user->user_id;
                $user = DB::table('users')->where('id', $user_id)->first();
//                dd($user[0]->name);
                $single_user->name =  $user->name;
                $single_user->city = $user->city;
                $single_user->url = "bevylife/#/admin/users/".$user_id."/edit";
                $member[$num] = $single_user;
                $num++;
            }
            return $member; 
        }else{
            return "Please priovide id";
        }
        
    }

    /*
    * get all categories based on given params 
    * used for userside, for all group page.
    */

    public function getAllCatByword(Request $request)
        {
           // dd($request['arg_token']);//arg_token
            $bsqd = $request['term'];
            $categories = Category::where('category_name','like','%'.$bsqd.'%')->select('categories.category_name')->get()->toArray();
            $Arr=[];
            foreach ($categories as $key => $value) {
                $Arr[]=$value['category_name'];
            }
            return $Arr;
        }


     /*
    * get all categories 
    * used for userside, for home page index to get the image.
    */

    public function randCategory(Request $request)
	{
	
		$categories = Category::limit(7)->get();
		$row=[];
		foreach ($categories as $key => $value) {
			$row[$value['category_name']] = $value['avatar_url'];
			
		}

		return $row;
	}

    public function allSetCategory(Request $request)
	{
	
		$categories = Category::all();
		$row=[];
		foreach ($categories as $key => $value) {
			$row[$value['category_name']] = $value['avatar_url'];
			
		}

		return $row;
	}


	public function cmsDetail(Request $request)
	{
		
		$categories = CmsTemplate::find(1);
		$cms_images = CmsTemplateImage::select('image')->where('cms_template_id', '=',1)->first();
		 
		$data=array();
		$data['page_heading']=$categories->page_heading;
		$data['sub_heading']=$categories->sub_heading;
		$data['description']=$categories->description;
		$data['image']=$cms_images->image;
		 
		return $data;
	}   

    /*
    * get all categories based on given params 
    * used for userside, for all group page always filtered by locaion and area of interest.
    */

    public function getGroupByInterest(Request $request)
	{
		
		$interestfilter = isset($request['interest'])?$request['interest']:"null";

		if(isset($request['latitude']) && $request['latitude'] !=""){
			$lng= $request['latitude'];
			$lat = $request['longitude'];
			$distance=25;
		}

		$per_page = \Request::get('per_page') ?: 10;
	
		if ($lat) {
			 $products = DB::table('categories')
			->select('groups.*')
			->join('group_interests', 'group_interests.category_id', '=', 'categories.id')
			->join('groups', 'groups.id', '=', 'group_interests.group_id')
			->selectRaw('( 6371 * acos( cos( radians(?) ) *
							   cos( radians( groups.latitude ) )
							   * cos( radians( groups.longitude ) - radians(?)
							   ) + sin( radians(?) ) *
							   sin( radians( groups.latitude ) ) )
							 ) AS distance', [$lng,$lat,$lng]);
			
			
				  
			if(isset($interestfilter !=''){
				$filtered=$request['interest'];
				$products->where('categories.category_name','like','%'.$filtered.'%');
			}
			$products->where('groups.status',1);   
			$products->havingRaw("distance < ?",[$distance]);
			$result = $products->orderBy('created_at','desc');
			$result = $products->groupBy('groups.id');
			$result = $products->simplePaginate($per_page);    
			return  $result;
		}
		return 'not found';
	}
           
	public function test(Request $request)
	{
		$lat = '77.33257';
		$lng = '28.6455348';
		$distance=25;
	  
		$products = DB::table('categories')
			->select('groups.*')
			->join('group_interests', 'group_interests.category_id', '=', 'categories.id')
			->join('groups', 'groups.id', '=', 'group_interests.group_id')
			->selectRaw('( 6371 * acos( cos( radians(?) ) *
							   cos( radians( groups.latitude ) )
							   * cos( radians( groups.longitude ) - radians(?)
							   ) + sin( radians(?) ) *
							   sin( radians( groups.latitude ) ) )
							 ) AS distance', [$lng,$lat,$lng])
			->where('groups.status',1)
			->havingRaw("distance < ?",[$distance]);
			
		$p = $products->simplePaginate(15);
		return $p;
	}

	public function category(Request $request)
	{
	   $categories = Category::select('categories.category_name','categories.id')->get();
		return $categories;
	}

	public function index_group()
	{
		 $groupdata=Group::orderBy('created_at', 'asec')->first();
		
		 $id = $groupdata['id'];
		 
		$image = GroupImage::select('image')->where('group_id','=',$id)->get();
		$groupdata['images'] = $image;
		return $groupdata;
	}
	
	public function get_event(Request $request)
	{
		 $event_id = $request['id'];
		 if($event_id){
			$group_interest=DB::table('group_events')
				   ->join('groups', 'group_events.group_id', '=', 'groups.id')
				   ->where('group_events.id', '=', $event_id)
				   ->select('group_events.*','groups.name as group_name','groups.id as group_id','groups.header as group_header','groups.description as group_description','groups.avatar_url')
				   ->first();
			
			$group_id = $group_interest->group_id;
			
			$images = GroupImage::select('image')->where('group_id','=',$group_id)->get();
			
			$data['group_event'] = $group_interest;
			$data['images'] = $images;
		   
			return $data;
			
		}   
	}

	public function getEventByDate(Request $request)
	{
		$event_date = $request['date'];
		if($event_date)
		{			
			$group_interest=DB::table('group_events')
				   ->join('groups', 'group_events.group_id', '=', 'groups.id')
				   ->where('group_events.id', '=', $event_id)
				   ->select('group_events.*','groups.name as group_name','groups.id as group_id','groups.header as group_header','groups.description as group_description','groups.avatar_url')
				   ->orderBy('created_at','desc')
				   ->get();
			
			$group_id = $group_interest->group_id;
			
			$images = GroupImage::select('image')->where('group_id','=',$group_id)->get();
			
			$data['group_event'] = $group_interest;
			$data['images'] = $images;
		   
			return $data;		
		}   
	}
}
