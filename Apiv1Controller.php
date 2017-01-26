<?php

// This is a rest api for item module where user add.edit,show and delete Item.

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use Auth;
use Validator;
use App\User;
use App\Item;


class Apiv1Controller extends Controller {

    // This api get all item that are listed  in item table.
	
	
    public function index() {
        if (!Auth::check()) {

		 return response()->json(['error' => '', 'message' => 'Unauthorized login'], 401);
            
        } else {

            $account_id = Auth::user()->account_id;
            $item = Items::where(array('account_id' => $account_id, 'active' => 1))->with('category', 'status', ' owner.user', 'location', 'site', 'supplier', 'photo', 'pat')->get()->toarray();
            return response()->json(['error' => '', 'message' => '' , 'data'=>$item], 401);
            
        }
    }

	
    // This api use when user want to add new item in system.
	
	
    public function store() {
        if (!Auth::check()) {
            	 return response()->json(['error' => '', 'message' => 'Unauthorized login'], 401);
        } else {
            if (Input::all()) {
                $rules = array(
                    'item_barcode' => 'required|unique:items',
                    'category_id' => 'required',
                    'status_id' => 'required',
                    'serial_number' => 'required',
                    'owner_id' => 'required',
                    'item_manufacturer' => 'required',
                    'item_model' => 'required',
                    'location_id' => 'required',
                    'site_id' => 'required',
                    'item_purchased_date' => 'required|date_format:Y-m-d',
                    'item_warranty_date' => 'required|date_format:Y-m-d',
                    'item_replace_date' => 'required|date_format:Y-m-d',
                    'item_patteststatus' => 'required',
                    'supplier_id' => 'required',
                    'photo' => 'required',
                );
                $validator = Validator::make(Input::all(), $rules);
                if ($validator->fails()) {
					return response()->json(['error' =>$validator->errors(), 'message' => 'Could not create new item'], 402);
                } else {
                    if ($this->input->post('item_quantity') == '') {
                        $quantity = 1;
                    } else {
                        $quantity = $this->input->post('item_quantity');
                    }
                    $arrItemData = array(
                        'barcode' => $this->input->post('item_barcode'),
                        'serial_number' => $this->input->post('serial_number'),
                        'manufacturer' => $this->input->post('item_manufacturer'),
                        'model' => $this->input->post('item_model'),
                        'site' => $this->input->post('site_id'),
                        'account_id' => Auth::user()->accountid,
                        'owner_id' => $this->input->post('owner_id'),
                        'purchase_price' => $this->input->post('purchase_price') * $quantity,
                        'status_id' => $this->input->post('status_id'),
                        'purchase_date' => $this->doFormatDate($this->input->post('item_purchased_date')),
                        'warranty_date' => $this->doFormatDate($this->input->post('item_warranty_date')),
                        'replace_date' => $this->doFormatDate($this->input->post('item_replace_date')),
                        'pattest_status' => $this->input->post('item_patteststatus'),
                        'quantity' => $quantity,
                        'supplier' => $this->input->post('supplier_id'),
                        'created_at' => date('Y-m-d H:i:s')
                    );

                    if (Input::hasFile('photo')) {

                        $name = Input::file('photo')->getClientOriginalName();
                        $extension = Input::file('photo')->getClientOriginalExtension();
                        $size = Input::file('photo')->getSize();
                        $path = Input::file('photo')->getRealPath();
                        $destination = URL::asset('images');

                        if ($extension == 'jpeg' || $extension == 'jpg' || $extension == 'png') {
                            $imagedata = array(
                                'name' => $name,
                                'size' => $size,
                                'type' => $extension,
                                'path' => $destination . '/' . $name
                            );
                            $photo_id = Photo::insertGetId($imagedata);

                            if ($photo_id) {
                                $destinationPath = public_path() . '\images';
                                Input::file('photo')->move($destinationPath, $name);
                            }
                        } else {
                            $photo_id = null;
                        }
                    } else {
                        $photo_id = null;
                    }

                    $arrItemData['photo_id'] = $photo_id;
                    $item_id = Items::insertGetId($arrItemData);
                    if ($item_id) {
                        	return response()->json(['error' =>'', 'message' => 'Item add successfully'],200);
                    } else {
						    return response()->json(['error' =>'', 'message' => 'something went wrong'],402);
                        	
                    }
                    
                }
            } else {
                  return response()->json(['error' =>'', 'message' => 'something went wrong'],402);
            }
        }
    }

	
	// This api use when user want to get item detail
	
	
    public function show($item_id) {
        if (!Auth::check()) {
           	 return response()->json(['error' => '', 'message' => 'Unauthorized login'], 401);
        } else {
            $item = Items::with('category', 'status', 'owner.user', 'location', 'site', 'supplier', 'photo', 'pat')->where(array('id' => $item_id, 'active' => 1))->get()->toarray();

            if (!empty($item)) {
                     	return response()->json(['error' =>'', 'message' => '','data'=>$item],200);
        
            } else {
                 return response()->json(['error' =>'', 'message' => 'Item not found'],400);
            }
        }
    }

	
    // This api use when to get item detail for edit.
	
	
    public function edit($item_id) {
        if (!Auth::check()) {
            return response()->json(['error' => '', 'message' => 'Unauthorized login'], 401);
        } else {
            $item = Items::with('category', 'status', 'owner.user', 'location', 'site', 'supplier', 'photo', 'pat')->where(array('id' => $item_id, 'active' => 1))->get()->toarray();
            if (!empty($item)) {
               	return response()->json(['error' =>'', 'message' => '','data'=>$item],200);
            } else {
                return response()->json(['error' =>'', 'message' => 'Item not found'],400);
            }
        }
    }

	// This api use when user want to update item detail.
		
		
    public function update($item_id) {

        if (!Auth::check()) {
            $result = $this->createResponse(false, 'Unauthorized login', '401', 'Update Item');
            echo $result;
        } else {
            $item_data = json_decode(file_get_contents("php://input"), true);

            $rules = array(
                'category_id' => 'required',
                'status_id' => 'required',
                'serial_number' => 'required',
                'owner_id' => 'required',
                'item_manufacturer' => 'required',
                'item_model' => 'required',
                'location_id' => 'required',
                'site_id' => 'required',
                'item_purchased_date' => 'required|date_format:Y-m-d',
                'item_warranty_date' => 'required|date_format:Y-m-d',
                'item_replace_date' => 'required|date_format:Y-m-d',
                'item_patteststatus' => 'required',
                'supplier_id' => 'required',
                'photo' => 'required',
            );

            $validator = Validator::make($item_data, $rules);

            if ($validator->fails()) {
                		return response()->json(['error' =>$validator->errors(), 'message' => 'Could not update item'], 402);
            } else {

                $arrItemData = array(
                    'serial_number' => $this->input->post('serial_number'),
                    'manufacturer' => $this->input->post('item_manufacturer'),
                    'model' => $this->input->post('item_model'),
                    'owner_is' => $this->input->post('owner_id'),
                    'site' => $this->input->post('site_id'),
                    'account_id' => Auth::user()->accountid,
                    'purchase_price' => $this->input->post('purchase_price') * $quantity,
                    'status_id' => $this->input->post('status_id'),
                    'purchase_date' => $this->doFormatDate($this->input->post('item_purchased_date')),
                    'warranty_date' => $this->doFormatDate($this->input->post('item_warranty_date')),
                    'replace_date' => $this->doFormatDate($this->input->post('item_replace_date')),
                    'pattest_status' => $this->input->post('item_patteststatus'),
                    'quantity' => $quantity,
                    'supplier' => $this->input->post('supplier_id'),
                    'created_at' => date('Y-m-d H:i:s')
                );

                if (Input::hasFile('photo')) {
                    $name = Input::file('photo')->getClientOriginalName();
                    $extension = Input::file('photo')->getClientOriginalExtension();
                    $size = Input::file('photo')->getSize();
                    $path = Input::file('photo')->getRealPath();
                    $destination = URL::asset('images');

                    $imagedata = array(
                        'name' => $name,
                        'size' => $size,
                        'type' => $extension,
                        'path' => $destination . '/' . $name
                    );

                    $photo_id = Image::insertGetId($imagedata);
                    if ($photo_id) {
                        $destinationPath = public_path() . '\images';

                        Input::file('photo')->move($destinationPath, $name);
                    } else {
                        $photo_id = null;
                    }
                }
                $arrItemData['photo_id'] = $photo_id;
                $arrItemData['updated_at'] = date('Y-m-d h:i:s');
                $update_result = DB::table('items')
                        ->where('id', $item_id)
                        ->where('account_id', Auth::user()->account_id)
                        ->update($arrItemData);

                // redirect
                if ($update_result) {
                   	return response()->json(['error' =>'', 'message' => 'Item update successfully'], 200);
                } else {
                           	return response()->json(['error' =>'', 'message' => 'Item not exists'], 400);
                }
            }
        }
    }

	
	// This api use when user want to delete item in system.
	
	
    public function destroy($item_id) {
        if (!Auth::check()) {
            $result = $this->createResponse(false, "Unauthorized login", '401', 'Delete Item');
            echo $result;
        } else {
            $delete_item = DB::table('items')
                    ->where('id', $item_id)
                    ->where('account_id', Auth::user()->account_id)
                    ->update(array('active' => 0));

            if ($delete_item) {
                  	return response()->json(['error' =>'', 'message' => 'Item delete successfully'], 200);
           
            } 
			    return response()->json(['error' =>'', 'message' => 'Does not exist'], 400);
             
            }
        }
    }



}
