<?php
namespace App\Http\Libraries;
use Illuminate\Support\Facades\DB;
use Auth;
use Input;
use Session;
use App\Http\Models\RequestStock;
use App\Http\Models\RequestStockMapping;
use App\Http\Models\User;
use App\Http\Models\UserType;
use App\Http\Models\Location;
use App\Http\Models\Suburb;
use App\Http\Models\Store;
use App\Http\Models\UserMapping;
use App\Http\Models\StoreLocationMapping;
use App\Http\Models\AccessLevel;
use App\Http\Models\Duration;
use App\Http\Models\HoldStock;
use App\Http\Models\HoldStockMapping;
use App\Http\Models\MaintainRequest;
use App\Http\Models\Transfer;
use App\Http\Models\Product;
use App\Http\Models\ProductType;
use App\Http\Models\ProductSubType;
use App\Http\Models\productMapping;
use App\Http\Models\ProductLocationMapping;
use App\Http\Libraries\Mailer;
use App\Http\Models\Notification;


class Helper {

        public static function AuthAccess($level) {
                
                 /*
                        Access Level 1  Only for Admin
                        Access Level 2  Only Company Admin
                        Access Level 3  Company Admin & Store Manager
                        Access Level 4  Company Admin & Store Manager & Staff    
                        Access Level 5  Company Admin & Store Manager & Staff  & Customers
                        Access Level 6  Store Manager & Staff
                        Access Level 7  Admin  & Company Admin
                  *    Access Level 8  Only for Store Manager
                  *    Access Level 9  Only for Staff
                  * 
                  */

                if(Auth::check())
                {
                        $level_id = \Config::get('default.level.'.$level);
                        $auth=  Auth::user();
                        $auth_mapping = UserMapping::where('user_id','=',$auth->id)->where('user_type_id','!=','4')->get();   
            
                        switch ($level_id) {
                                case 1:
                                        foreach ($auth_mapping as $auth_mapping)
                                        {
                                                $user_type = UserType::where('id','=',$auth_mapping->user_type_id)->first();
                                              
                                                
                                                if($user_type->name == 'admin')
                                                {
                                                        return $user_type->name;
                                                }
                                                else 
                                                {
                                                        return false;
                                                }    
                                        }

                                        break;
                                case 2:
                                        foreach ($auth_mapping as $auth_mapping)
                                        {
                                                $user_type = UserType::where('id','=',$auth_mapping->user_type_id)->first();
                                                
                                                if($user_type->name == 'company_admin')
                                                {
                                                        return true;
                                                }
                                                else 
                                                {
                                                        return false;
                                                }    
                                        }
                                        break;
                                case 3:
                                         foreach ($auth_mapping as $auth_mapping)
                                        {
                                                $user_type = UserType::where('id','=',$auth_mapping->user_type_id)->first();
                                                
                                                if($user_type->name == 'company_admin')
                                                {
                                                        return true;
                                                }
                                                else if($user_type->name == 'store_manager')
                                                {
                                                        return true;
                                                }
                                                else 
                                                {
                                                        return false;
                                                }    
                                        }
                                        break;
                                case 4:
                                         foreach ($auth_mapping as $auth_mapping)
                                        {
                                                $user_type = UserType::where('id','=',$auth_mapping->user_type_id)->first();
                                                
                                                if($user_type->name == 'company_admin')
                                                {
                                                        return true;
                                                }
                                                else if($user_type->name == 'store_manager')
                                                {
                                                        return true;
                                                }
                                                 else if($user_type->name == 'staff')
                                                {
                                                        return true;
                                                }
                                                else 
                                                {
                                                        return false;
                                                }    
                                        }
                                        break;
                                case 5:
                                         foreach ($auth_mapping as $auth_mapping)
                                        {
                                                $user_type = UserType::where('id','=',$auth_mapping->user_type_id)->first();
                                                
                                                if($user_type->name == 'company_admin')
                                                {
                                                        return true;
                                                }
                                                else if($user_type->name == 'store_manager')
                                                {
                                                        return true;
                                                }
                                                else if($user_type->name == 'staff')
                                                {
                                                        return true;
                                                }
                                                else if($user_type->name == 'customer')
                                                {
                                                        return true;
                                                }
                                                else 
                                                {
                                                        return false;
                                                }    
                                        }
                                        break;
                                case 6:
                                         foreach ($auth_mapping as $auth_mapping)
                                        {
                                                $user_type = UserType::where('id','=',$auth_mapping->user_type_id)->first();

                                                if($user_type->name == 'store_manager')
                                                {
                                                        return true;
                                                }
                                                else if($user_type->name == 'staff')
                                                {
                                                        return true;
                                                }
                                                else 
                                                {
                                                        return false;
                                                }    
                                        }
                                        break;
                                        
                                 case 7:
                                         foreach ($auth_mapping as $auth_mapping)
                                        {
                                                $user_type = UserType::where('id','=',$auth_mapping->user_type_id)->first();
                                                
                                                if($user_type->name == 'admin')
                                                {
                                                        return true;
                                                }
                                                else if($user_type->name == 'company_admin')
                                                {
                                                        return true;
                                                }
                                                else 
                                                {
                                                        return false;
                                                }    
                                        }
                                        break;
                                case 8:
                                         foreach ($auth_mapping as $auth_mapping)
                                        {
                                                $user_type = UserType::where('id','=',$auth_mapping->user_type_id)->first();
                                                
                                                if($user_type->name == 'store_manager')
                                                {
                                                        return true;
                                                }
                                                else 
                                                {
                                                        return false;
                                                }    
                                        }
                                        break;
                                case 9:
                                         foreach ($auth_mapping as $auth_mapping)
                                        {
                                                $user_type = UserType::where('id','=',$auth_mapping->user_type_id)->first();
                                                
                                                if($user_type->name == 'staff')
                                                {
                                                        return true;
                                                }
                                                else 
                                                {
                                                        return false;
                                                }    
                                        }
                                        break;
                                        
                                default:
                                return false;
                        }
                }
                else {
                        return false;
                }

        }
        
        /*
         * To Get Logged in user company logo to view in pages
         */
        public static  function storeLogo($store_id) {
            
                $storeLogo = Store::where('id','=',$store_id)->first();
                
                if(isset($storeLogo) && !empty($storeLogo))
                {
                        return $storeLogo->logo_vertical;
                }
                return 'assets/img/logo-v.png';
            
        }
        
          /*
         * To Get Logged in user company Name to view in pages
         */
        public static  function storeName($store_id) {
            
                $storeName = Store::where('id','=',$store_id)->first();
                
                if(isset($storeName) && !empty($storeName))
                {
                        return $storeName->store_name;
                }
                return 'Seek';
            
        }
        
        
        
          /*
         * To Get Logged in user logo to view in pages
         */
         public static  function userLogo($user_id) {
            
                $userLogo = User::where('id','=',$user_id)->first();
                
                if(isset($userLogo) && !empty($userLogo))
                {
                        return $userLogo->profile_picture;
                }
                return 'assets/img/user.png';
            
        }
        
          /*
         * To Get Logged in user logo to view in pages
         */
         public static  function userType($user_id) {
            
                $userLogo = User::where('id','=',$user_id)->first();
                
                $user_mapping = UserMapping::where('user_id','=',$user_id)->where('user_type_id','!=','4')->get();
                
                
                foreach($user_mapping as $user_mappings)
                {
                        $user_type[] = UserType::where('id','=',$user_mappings->user_type_id)->first();
                }
                
                $i=0;
                $count = count($user_type);
                
                foreach ($user_type as $user_types){
                        $values =     $user_types->user_type; 
                }   
               
                $json_encode = json_encode($values);
                $replace = str_replace(("/'/"), "", $json_encode);
                                
                
                return $values;
        }
        
        
         /*
         * Find Suburb using suburb ID
         */
        
         public static  function findLocation($location_id) {
            
                $values = Suburb::where('id','=',$location_id)->first();
                $location = $values->suburb;
                return $location;
        }
        
        /*
         * 
         * Prem Start
         * Change Status by orders
         */
        public static  function stockStatus($status,$from,$to,$id) {
                
                /*
                $user = StoreLocationMapping::where('id','=',Auth::user()->id)
                                ->where('store_id','=',Auth::user()->store_id)
                                ->first();
                */
                 //Get all information from user table and join store location table using user id
                $user_store= DB::table('users')
                                                //Join Store location table using user store location id and get store name & location name
                                                ->join('store_location_mapping as store', 'store.id','=', 'users.store_location_mapping_id')
                                                ->where('users.id','=',Auth::user()->id)
                                                ->first();
                
                //checking store location is equal to request location
                if($user_store->suburb_id == $from )
                {       
                        $status = MaintainRequest::where('hold_stock_mapping_id','=',$id)->first();
                                if($status->status == 8)
                                {      
                                        $value = Config('default.whishlist_reverse.16');
                                        return $value;
                                }
                                else if($status->status == 5)
                                {      
                                        $value = Config('default.whishlist_reverse.10');
                                        return $value;
                                }
                                else
                                {
                                        $value = Config('default.whishlist_reverse.'.$status->status);
                                        return $value;
                                }

                }
                else if($user_store->suburb_id == $to)
                {
                                $status = MaintainRequest::where('hold_stock_mapping_id','=',$id)->first();
                                if($status->status == 8)
                                {      
                                        $value = Config('default.whishlist_reverse.1');
                                        return $value;
                                }
                                else if($status->status == 5)
                                {      
                                        $value = Config('default.whishlist_reverse.5');
                                        return $value;
                                }
                                else
                                {
                                        $value = Config('default.whishlist_reverse.'.$status->status);
                                        return $value;
                                }
                }
                else
                {
                        return 'Error';
                }
        }
        
        
        /*
         * 
         * Prem Start
         * Change type of the hold & Transfer by orders
         */
        public static  function stockType($status,$from,$to,$id) {
                
                /*
                $user = StoreLocationMapping::where('id','=',Auth::user()->id)
                                ->where('store_id','=',Auth::user()->store_id)
                                ->first();
                */
                
                //Get all information from user table and join store location table using user id
                $user_store= DB::table('users')
                                        //Join Store location table using user store location id and get store name & location name
                                        ->join('store_location_mapping as store', 'store.id','=', 'users.store_location_mapping_id')
                                        ->where('users.id','=',Auth::user()->id)
                                        ->first();
                
                
                         //checking store location is equal to request location
                if($user_store->suburb_id == $from )
                {       
                        $status = MaintainRequest::where('hold_stock_mapping_id','=',$id)->first();
                                //check status is 'Request' if so then it is outgoing request
                                if($status->type == 8)
                                {      
                                        $value = Config('default.whishlist_reverse.21');
                                        return $value;
                                }
                                else
                                {
                                        $value = 'Store Hold';
                                        return $value;
                                }

                }
                else if($user_store->suburb_id == $to)
                {
                                $status = MaintainRequest::where('hold_stock_mapping_id','=',$id)->first();
                                if($status->type == 8)
                                {      
                                        $value = Config('default.whishlist_reverse.15');
                                        return $value;
                                }
                                else
                                {
                                        $value = 'Store Hold';
                                        return $value;
                                }
                }
                else
                {
                        return 'Error';
                }
        }
        
        
}

