<?php

class ApiForItems extends \BaseController {

    public function index() {
        if (!Auth::check()) {
            $result = $this->createResponse(false, "Unauthorized login", '401', 'Get all items');
            echo $result;
        } else {

            $account_id = Auth::user()->account_id;

            $item = Items::where(array('account_id' => $account_id, 'active' => 1))->with('category', 'status', ' owner.user', 'location', 'site', 'supplier', 'photo', 'pat')->get()->toarray();

            $result = $this->createResponse($item, "OK", '200', 'Get all items');
            echo $result;
        }
    }

    public function store() {
        if (!Auth::check()) {
            $result = $this->createResponse(false, "Unauthorized login", '401', 'Add Item');
            echo $result;
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
                    $result = $this->createResponse(false, 'Generic bad request', '400', 'Add Item');
                    echo $result;
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
                        $result = $this->createResponse($item_id, 'Item Added Successfully', 201, 'Add Item');
                    } else {
                        $result = $this->createResponse(false, 'Generic bad request', '400', 'Add Item');
                    }
                    echo $result;
                }
            } else {
                $result = $this->createResponse(false, 'Generic bad request', '400', 'Add Item');
                echo $result;
            }
        }
    }

    public function show($item_id) {
        if (!Auth::check()) {
            $result = $this->createResponse(false, 'Unauthorized login', '401', 'Show Item');
            echo $result;
        } else {
            $item = Items::with('category', 'status', 'owner.user', 'location', 'site', 'supplier', 'photo', 'pat')->where(array('id' => $item_id, 'active' => 1))->get()->toarray();

            if (!empty($item)) {
                $result = $this->createResponse($item, 'OK', 200, 'Show Item');
                echo $result;
            } else {
                $result = $this->createResponse(false, 'Does not exist', '400', 'Show Item');
                echo $result;
            }
        }
    }

    public function edit($item_id) {
        if (!Auth::check()) {
            $result = $this->createResponse(false, "Unauthorized login", '401', 'Edit Item');
            echo $result;
        } else {
            $item = Items::with('category', 'status', 'owner.user', 'location', 'site', 'supplier', 'photo', 'pat')->where(array('id' => $item_id, 'active' => 1))->get()->toarray();
            if (!empty($item)) {
                $result = $this->createResponse($item, "OK", 200, 'Edit Item');
                echo $result;
            } else {
                $result = $this->createResponse(false, "Does not exist", '400', 'Edit Item');
                echo $result;
            }
        }
    }

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
                $result = $this->createResponse(false, "Generic bad request", '400', 'Update Item');
                echo $result;
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
                    $result = $this->createResponse($update_result, 'Item Successfully Updated', 201, 'Update Item');
                    echo $result;
                } else {
                    $result = $this->createResponse(false, "Does not exist", '400', 'Update Item');
                    echo $result;
                }
            }
        }
    }

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
                $result = $this->createResponse($delete_item, "Item Successfully Remove", 200, 'Delete Item');
                echo $result;
            } else {
                $result = $this->createResponse(false, "Does not exist", '400', 'Delete Item');
                echo $result;
            }
        }
    }

    function createResponse($data = false, $messgae = false, $code = false, $type = false) {

        return Response::json([
                    'data' => [
                        'result' => $data,
                        'message' => $messgae,
                        'type' => $type,
                    ]
                        ], $code);
    }

}
