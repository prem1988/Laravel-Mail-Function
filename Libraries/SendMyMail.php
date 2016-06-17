<?php

namespace App\Http\Libraries;
use Weblee\Mandrill\Mail;
use App\Http\Models\EmailTemplates;
use App\Http\Models\Whistlist;
use App\Http\Models\WhistlistTransfer;
use App\Http\Models\Suburb;
use App\Http\Models\StoreLocationMapping;
use App\Http\Models\HoldStockMapping;
use App\Http\Models\ProductLocationMapping;
use App\Http\Models\HoldStock;
use App\Http\Models\Duration;
use App\Http\Models\ProductType;
use App\Http\Models\Size;
use App\Http\Models\Store;
use App\Http\Models\Color;
use App\Http\Models\MaintainRequest;
use Auth;
use Illuminate\Support\Facades\URL;
//use Illuminate\Support\Facades\Mail;
use App\Exceptions\emailExceptions;

class SendMyMail{

        private $mandrill;
        private $subject;
        private $body;
        private $to;
        private $cc;
        private $attachments;

        public function __construct()
        {
                //Madril Sending Mail Using API
                $mandrill = new \Mandrill('38IOFfeqNEFbj1Do3ullhQ');
                $this->mandrill = $mandrill;
                $this->subject = "";
                $this->body = "";
                $this->to = array();
                $this->cc = array();
                $this->attachments = array();
        }

        
        public function set_subject($subject)
        {
                $this->subject = $subject;
        }

        public function set_body($body)
        {
                $this->body = $body;
        }

        public function set_destination($to)
        {
                $this->to = $this->_fill_recipients_array($to, 'to');
        }

        public function set_cc($cc)
        {
                $this->cc = $this->_fill_recipients_array($cc, 'cc');
        }

        private function _fill_recipients_array($recipients, $type)
        {
                $send_to = array();
                
                if (is_array($recipients)) {
                        foreach ($recipients as $r)
                                $send_to[] = array('email' => $r, 'type' => $type);
                } else
                        $send_to[] = array('email' => $recipients, 'type' => $type);

                return $send_to;
        }

        private function _fill_attachment_information($file)
        {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mymetype = finfo_file($finfo, $file);
                finfo_close($finfo);

                $file_name = explode("/", $file);
                $file_name = end($file_name);

                $this_attachment = array('type' => $mymetype,
                                         'name' => $file_name,
                                         'content' => base64_encode(file_get_contents($file)),
                                         );
                return $this_attachment;
        }


        public function set_attachments($files)
        {
                $attachment_array = array();
                if (is_array($files)) {
                        foreach ($files as $f)
                                $attachment_array[] = $this->_fill_attachment_information($f);
                } else
                        $attachment_array[] = $this->_fill_attachment_information($files);

                $this->attachments = $attachment_array;
        }

        public function send($from)
        {
                
                //Madril Sending Mail Using API
                $mandrill = new \Mandrill('38IOFfeqNEFbj1Do3ullhQ');
                
                //Using Madril Weblee development
                $message = array(
                        'html' => $this->body,
                        'text' => 'Mail send from SeekStock.co.nz',
                        'subject' =>$this->subject,
                        'from_email' => $from,
                        'from_name' => $from,
                        'to' => $this->to,
                        'attachments' => $this->attachments,
                        'headers' => array('Reply-To' => $from,
                                            'X-MC-Track'=> 'opens, clicks_textonly'),
                        'important' => TRUE,
                        'track_opens' => TRUE,
                        'track_clicks' => TRUE
                );

                $async = false;
                $send_at = null;
                $result = $mandrill->messages->send($message, $async, $send_at);
   
        }

        // ----- OR  -------    
        public function sendTemplate($data)
        {
                $this->mandrill->messages()->sendTemplate($data);
        }
        
        /**
        * Via method injection
        *
        */
        public function sendMail(Mail $mandrill, $data)
        {   
                $mandrill->messages()->sendTemplate($data);
        }

        // ----- OR  -------

        /**
        * Via the Facade
        *
        */
        public function sendMailByFacade($data)
        {
                 \MandrillMail::messages()->sendTemplate($data);
        }
}


/*****************************************************************************/
/*****************************************************************************/
class Mailer
{       
        /**
       * Get Email Tempalate from database from email_template table
       *
       * @return 
       */
        private static function _get_template($name)
        {
                $ret = EmailTemplates::where('name', '=', $name)->first()->value;
                $ret = nl2br($ret);
                return $ret;
        }
        
        /**
       * Merge Database Email tempale & array pass value from controller
       *
       * @return 
       */
        private static function _transform($rules, &$body)
        {
                foreach ($rules as $k => $v) {
                        $body = str_replace("[\$".$k."]", $v, $body);
                }
        }
        
        /**
        * Compain Master template & Body Templates
        *
        * @return
        */
        private static function _mail($subject, $content, $to)
        {      
                
                $auth= Auth::User();
                if($auth)
                {
                $store = \App\Http\Models\Store::where('id','=',$auth->store_id)->first();
                
                $from = array();
                //$from['email']= $store->email;
                $from['email']= 'support@seekstock.nz';
                $from['store']= $store->store_name;
                
                $message = static::_get_template('master_template');
                $transform_set = array('logo' => asset($store->logo_vertical),
                                       'store' =>$store->store_name ,
                                       'body' => $content,
                                       'year' => date("Y"));
                }
                else {
                 $from = array();   
                 $from['email']= 'support@seekstock.nz';   
                 $from['store']= 'SeekStock';  
                 
                $message = static::_get_template('master_template');
                $transform_set = array('logo' =>'',
                                       'store' =>'' ,
                                       'body' => $content,
                                       'year' => date("Y"));
                 
                }
                static::_transform($transform_set, $message);
                static::_mandrill_send($subject, $message, $to, $from);

        }
        
        
        /**
       * Send Message Using mandrill app api.. 
       *
       * @return \Illuminate\Http\Response
       */
        private static function _mandrill_send($subject, $body, $to, $from, $cc=[])
        {
                /*
                        Mail::send('email.test', array('name'=>'Admin'), function($message) {
                                $message->to('premrajah_2004@yahoo.com', 'Hello');
                                $message->from('tharmarajah@gmail.com');
                                $message->subject('Welcome to the Laravel App!');
                        });
                 * 
                 */     
            
                if (is_array($to)) {
                        $send_to = array();
                        foreach ($to as $t)
                                $send_to[] = array('email' => $t, 'type' => 'to');
                        $send_to += $cc;
                } else
                {
                        $send_to = array(array('email' => $to, 'type'=> 'to')) + $cc;
                }
                
                //Madril Sending Mail Using API
                $mandrill = new \Mandrill('38IOFfeqNEFbj1Do3ullhQ');
                //Using Madril Weblee development
                $message = array(
                        'html' => $body,
                        'text' => 'Mail Sending From SeekStock..nz',
                        'subject' =>$subject,
                        'from_email' => $from['email'],
                        'from_name' => $from['store'],
                        'to' => $send_to,
                        'headers' => array('Reply-To' => $from,
                                            'X-MC-Track'=> 'opens, clicks_textonly'),
                        'important' => TRUE,
                        'track_opens' => TRUE,
                        'track_clicks' => TRUE
                );

                $async = false;
                $send_at = null;
                $result = $mandrill->messages->send($message, $async, $send_at);
                //echo "<pre>".print_r($result,1)."</pre>";
        }   
        
        
        /*
         * Fuction For Email SeekStock bugs to seekstock@gmail.com
         * 
         * Sending Bugs Report Seek Stock Asana  Created By Prem
         * 
         * 
         *   */
        
        public static function send_feedback_asana($url, $feedback, $screen_shot)
        {       
                
                $sender = new SendMyMail();

                $sender->set_subject('Feedback report : '.$feedback);
         
                $body = sprintf("From user %s\nURL: %s\n\n%s", Auth::user()->name, $url, $feedback);
                $sender->set_body($body);

                $sender->set_destination(['seekstock@gmail.com']);
                //$sender->set_destination(['x+18097744762552@mail.asana.com', 'afreshimage@gmail.com']);

                $cc = array(
                        array('email' => 'premrajah_2004@yahoo.com', 'type' => 'cc'),
                        array('email' => 'tharmarajah@gmail.com', 'type' => 'cc'),
                );

                $sender->set_cc('premrajah_2004@yahoo.com');

                if ($screen_shot !== null)
                {
                        $sender->set_attachments($screen_shot);
                }

                $prem = $sender->send('feedback@seekstock.nz');
                
                
                
                return $prem ;
        }

        
        /**
       * Send User Confirm token email class
       *
       * @return \Illuminate\Http\Response
       */
        
        public static function resend_user_confirmation_token($user)
        {

                $transform_set = array('name' => $user['name'],
                                                  'confirm_link' => $user['confirmation_code'],
                                              );

                $subject ='Please confirm your account';
                $content = static::_get_template('resend_user_confirmation_code');

                static::_transform($transform_set, $content);

                static::_mail($subject, $content, $user['email_to']);

        }  
        
        public static function whistlist_email_to_customer($wishlists_check , $customer)        
        {   
           
                $status = Config('default.whishlist_reverse.'.$wishlists_check->status);
                $type = $wishlists_check->type;
                if($type=='NEW')
                {    
                    
                      $html = '<div>';
                        $html .='<table style="width: 100%; color: #737373;  border: 1px solid gray;">';
                                $html .='<tr style="background: #83A9EA; color: white;">';
                                        $html .='<th style="width: 30%;">Product Type</th>';
                                        $html .='<th style="width: 20%;">Size</th>';
                                        $html .='<th style="width: 20%;">Color</th>';
                                        $html .='<th style="width: 10%;">Date</th>';
                                $html .='</tr>';
                    
                                $product_type =$wishlists_check->product_type_id;
                                $product_type = ProductType::where('id','=',$product_type)->first();
                                $size  = $wishlists_check->size_id;
                                $color = $wishlists_check->color_id;
                               if($size!=0)
                               {  

                                    $size  = Size::where('id','=',$size)->first();
                                    $size_name = $size->size_name;
                               }
                                else
                                {
                                    $size_name = 'N/A';
                                }

                                if($color!=0)
                               {  

                                    $color  = Color::where('id','=',$color)->first();
                                    $color_name = $color->color;
                               }
                                else
                                {
                                        $color_name = 'N/A';
                                }

                                $html .='<tr>';
                                $html .='<td>'.$product_type->product_type_name.'</td>';
                                $html .='<td>'.$size_name.'</td>';
                                $html .='<td>'.$color_name.'</td>';
                                $html .='<td>'.$wishlists_check->created_at.'</td>';

                                $html .='</tr>';

                        $html .='</table>';
                        $html .='</div>';
                }
                else
                {
                       
                    $html = '<div>';
                        $html .='<table style="width: 100%; color: #737373;  border: 1px solid gray;">';
                                $html .='<tr style="background: #83A9EA; color: white;">';
                                        $html .='<th style="width: 30%;">Product Name</th>';
                                        $html .='<th style="width: 20%;">Sku Number</th>';
                                        $html .='<th style="width: 20%;">Quantity</th>';
                                        $html .='<th style="width: 10%;">Date</th>';
                                $html .='</tr>';
                    
                   

                        $product_id = $wishlists_check->product_location_mapping_id;
                        $product_mapping = ProductLocationMapping::where('id','=',$product_id)->first();


                                $html .='<tr>';
                                $html .='<td>'.$product_mapping->Description.'</td>';
                                $html .='<td>'.$product_mapping->sku_number.'</td>';
                                $html .='<td>'.$wishlists_check->quantity.'</td>';
                                $html .='<td>'.$wishlists_check->created_at.'</td>';

                                $html .='</tr>';

                        $html .='</table>';
                        $html .='</div>';

                }

                $transform_set = array(
                                                'first_name' => $customer->first_name,
                                                'link' => URL::route('requestWishlistTransferShow', $wishlists_check->id),
                                                'date'=>date('d-m-Y'), 
                                                'product_details' => $html,
                                                );

                $subject = "Great news! Your I Love Ugly Product Request is Available";
                
                $content = static::_get_template('whishlist_available');
                static::_transform($transform_set, $content);
                static::_mail($subject, $content, $customer->email);
            
        }
        
        /* email to customer when extend transfer cancelled  */
        
        public static function emailHoldToCustomerExtendedTransferCancel($holdstocks ,$customer)
        {
          $transform_set = array(
                'name' => $customer->name,
                'link' => URL::route('emailHoldExtendedTransferCancel', $holdstocks->id),
                 'date'=>date('d-m-Y'),       
              );  
          
           $subject = "Your extended transfer is  cancelled";
           $content = static::_get_template('holdstock_extended_cancelled');
           static::_transform($transform_set, $content);
            
           static::_mail($subject, $content, $customer->email);
        }
        
        /* forgot email reset */
        
        public static function forgotEmailCustomer($customer)
        {
          // echo  $customer->name;exit();
            $transform_set = array(
                'name' => $customer->name,
                'link' => URL::route('forgotResetPassworkLink'),
                 'date'=>date('d-m-Y'),       
              );  
          $from     = 'no-reply@seekstock.nz';
           $subject = "Reset Password";
           $content = static::_get_template('reset_password_link');
           static::_transform($transform_set, $content);
            
           static::_mail($subject, $content, $customer->email);  
            
        }
        
         public static function whistlist_email_unavailable_customer($wishlists_check , $customer)        
        {   
            
            $status = Config('default.whishlist_reverse.'.$wishlists_check->status);
            $website = "https://www.iloveugly.co.nz/";
             $type = $wishlists_check->type;
                if($type=='NEW')
                {            
                        $html = '<div>';
                        $html .='<table style="width: 100%; color: #737373;  border: 1px solid gray;">';
                                $html .='<tr style="background: #83A9EA; color: white;">';
                                        $html .='<th style="width: 30%;">Product Type</th>';
                                        $html .='<th style="width: 20%;">Size</th>';
                                        $html .='<th style="width: 20%;">Color</th>';
                                        $html .='<th style="width: 10%;">Date</th>';
                                $html .='</tr>';


                                $product_type =$wishlists_check->product_type_id;
                                $product_type = ProductType::where('id','=',$product_type)->first();
                                $size  = $wishlists_check->size_id;
                                $color = $wishlists_check->color_id;
                               if($size!=0)
                               {  

                                    $size  = Size::where('id','=',$size)->first();
                                    $size_name = $size->size_name;
                               }
                                else
                                {
                                    $size_name = 'N/A';
                                }

                                if($color!=0)
                               {  

                                    $color  = Color::where('id','=',$color)->first();
                                    $color_name = $color->color;
                               }
                                else
                                {
                                        $color_name = 'N/A';
                                }

                                $html .='<tr>';
                                $html .='<td>'.$product_type->product_type_name.'</td>';
                                $html .='<td>'.$size_name.'</td>';
                                $html .='<td>'.$color_name.'</td>';
                                $html .='<td>'.$wishlists_check->created_at.'</td>';

                                $html .='</tr>';

                        $html .='</table>';
                        $html .='</div>';
                }
                else
                {
                        $html = '<div>';
                        $html .='<table style="width: 100%; color: #737373;  border: 1px solid gray;">';
                                $html .='<tr style="background: #83A9EA; color: white;">';
                                        $html .='<th style="width: 30%;">Product Name</th>';
                                        $html .='<th style="width: 20%;">Sku Number</th>';
                                        $html .='<th style="width: 20%;">Quantity</th>';
                                        $html .='<th style="width: 10%;">Date</th>';
                                $html .='</tr>';

                        $product_id = $wishlists_check->product_location_mapping_id;
                        $product_mapping = ProductLocationMapping::where('id','=',$product_id)->first();


                                $html .='<tr>';
                                $html .='<td>'.$product_mapping->Description.'</td>';
                                $html .='<td>'.$product_mapping->sku_number.'</td>';
                                $html .='<td>'.$wishlists_check->quantity.'</td>';
                                $html .='<td>'.$wishlists_check->created_at.'</td>';

                                $html .='</tr>';

                        $html .='</table>';
                        $html .='</div>';

                }

                $transform_set = array(
                                                'first_name' => $customer->first_name,
                                               'date'=>date('d-m-Y'), 
                                                'product_details' => $html,
                                                'website'=>$website,
                                                );
            $subject = "I Love Ugly Product Request Not Fulfilled";
            $content = static::_get_template('whishlist_unavailable ');
            static::_transform($transform_set, $content);
            
            static::_mail($subject, $content, $customer->email);
            
        }
        
        /* whistlist request transfer sent to customer*/
         public static function whistlistRequestTransferEmailToCustomer($holdstocks, $customer)
        {
                    
            $html = '<div>';
                        $html .='<table style="width: 100%; color: #737373;  border: 1px solid gray;">';
                                $html .='<tr style="background: #83A9EA; color: white;">';
                                        $html .='<th style="width: 30%;">Product Name</th>';
                                        $html .='<th style="width: 20%;">Sku Number</th>';
                                        $html .='<th style="width: 20%;">Quantity</th>';
                                        $html .='<th style="width: 10%;">Date</th>';
                                $html .='</tr>';
          
                $storelocation  = StoreLocationMapping::where('suburb_id','=',$holdstocks->to_location)->first();
              // var_dump($storelocation);exit();
                $storelocation1  = StoreLocationMapping::where('suburb_id','=',$holdstocks->from_location)->first();
                 $store_name    = Suburb::where('id','=',$storelocation->suburb_id)->first(); 
                 $store_name1    = Suburb::where('id','=',$storelocation1->suburb_id)->first();
                 $to_store      = $store_name->suburb;
                 $from_store    = $store_name1->suburb;
                 //var_dump($store_name->suburb);exit();
                    $html .='<tr>';
                    $html .='<td>'.$holdstocks->product_name.'</td>';
                    $html .='<td>'.$holdstocks->sku_number.'</td>';
                    $html .='<td>'.$holdstocks->quantity.'</td>';
                    $html .='<td>'.$holdstocks->created_at.'</td>';
                    $html .='</tr>';
                
            $html .='</table>';
            $html .='</div>';
            
           
                       
            $transform_set = array(
                'first_name' => $customer->first_name,
                'product_details' => $html,
                'to_store'=>$to_store,
                'from_store'=>$from_store,
                                  );
            
            $subject = "New Transfer Request to [$store_name->suburb]";
            $content = static::_get_template('whistlist_transfer_sent_customer');
            static::_transform($transform_set, $content);
            
            static::_mail($subject, $content, $customer->email);   
        }
        
        
        
         public static function whistlist_email_notfullfill_customer($wishlists_check , $customer)        
        {   
            
            $status = Config('default.whishlist_reverse.'.$wishlists_check->status);
            
            $transform_set = array(
                'name' => $customer->name,
                                     );
            
            $subject = "Your request $status";
            $content = static::_get_template('whishlist_unavailable ');
            static::_transform($transform_set, $content);
            
            static::_mail($subject, $content, $customer->email);
            
        }
        
        public static function send_email_to_customer($wishlists_check , $customer)
        {
            $status = Config('default.whishlist_reverse.'.$wishlists_check->status);
            $whistlist_id = $wishlists_check->id;            
            $whistlist_transfer        = WhistlistTransfer::where('whistlist_id','=',$whistlist_id)->first();
            $store_location_mapping    = StoreLocationMapping::where('id','=',$whistlist_transfer->store_location_mapping_id)->first();
            $location                  = Suburb::where('id','=',$store_location_mapping->suburb_id)->first();
            $product                   = ProductType::where('id','=',$wishlists_check->product_type_id)->first();
                  
            $transform_set = array(
                'name' => $customer->name,
                'location'=> $location->suburb,
                'product'=>$product->product_type_name,
                                     );
            
            $subject = "Your ordered product is  $status";
            $content = static::_get_template('whishlist_received');
            static::_transform($transform_set, $content);
            
            static::_mail($subject, $content, $customer->email);  
        }
        
        /* email to customer when request is made */
        public static function holdstock_email_to_customer($holdstocks ,$customer)
        {
                        $html = '<div>';
                                $html .='<table style="width: 100%; color: #737373;  border: 1px solid gray;">';
                                        $html .='<tr style="background: #83A9EA; color: white;">';
                                                $html .='<th style="width: 30%;">Product Name</td>';
                                                $html .='<th style="width: 20%;">From Location</td>';
                                                $html .='<th style="width: 20%;">To Location</td>';
                                                $html .='<th style="width: 20%;">Hold Duration</td>';
                                                $html .='<th style="width: 10%;">Quantity</td>';
                                        $html .='</tr>';
                                        foreach($holdstocks as $holdstock)
                                        {
                                                $from_location = Suburb::where('id','=',$holdstock->from_location)->first();
                                                $to_location = Suburb::where('id','=',$holdstock->to_location)->first();
                                                $holdstocks   = HoldStock::where('id','=',$holdstock->hold_stock_id)->first();

                                                $html .='<tr>';
                                                        $html .='<td>'.$holdstock->product_name.'</td>';
                                                        $html .='<td>'.$from_location->suburb.'</td>';
                                                        $html .='<td>'.$to_location->suburb.'</td>';
                                                        $html .='<td>'.$holdstocks->duration.'</td>';
                                                        $html .='<td>'.$holdstock->quantity.'</td>';
                                                $html .='</tr>';
                                        }
                                $html .='</table>';
                        $html .='</div>';

                        $transform_set = array(
                                                        'first_name' => $customer->name,
                                                        'product_details' => $html,
                                                        'extend_link' => URL::route('postCancelHoldById', $holdstocks->id),
                                                        'cancel_link' => URL::route('postExtendedHoldById', $holdstocks->id),
                                                         );

                        $subject = "Your Hold is Now Active at I Love Ugly";
                        $content = static::_get_template('holdstock_ordered');

                        static::_transform($transform_set, $content);
                        static::_mail($subject, $content, $customer->email);
        }
        
         /* email to store when request is made */
        public static function holdstockEmailToStoreTransfer($holdstocks)
        {
                $holdstock_mappings = HoldStockMapping::where('hold_stock_id','=',$holdstocks)->where('status','=',8)->get(); 

                $hold_stock_data = array();

                foreach ($holdstock_mappings as $key => $value)
                {
                    $hold_stock_data[$value->from_location][] = $value;                
                }

                foreach ($hold_stock_data as $data_key => $hold_value)
                {
                        $store_location_mapping = StoreLocationMapping::where('suburb_id','=',$data_key)->first();     

                        $html = '<div>';
                                $html .='<table style="width: 100%; color: #737373;  border: 1px solid gray;">';
                                        $html .='<tr style="background: #83A9EA; color: white;">';
                                                $html .='<th style="width: 40%;">Product Name</th>';
                                                $html .='<th style="width: 40%;">Sku Number</th>';
                                                $html .='<th style="width: 20%;">Quantity</th>';
                                        $html .='</tr>';
                                        foreach($hold_value as $hold_values)
                                        {
                                                $html .='<tr>';
                                                        $html .='<td>'.$hold_values->product_name.'</td>';
                                                        $html .='<td>'.$hold_values->sku_number.'</td>';
                                                        $html .='<td>'.$hold_values->quantity.'</td>';
                                                $html .='</tr>';
                                        }
                                $html .='</table>';
                        $html .='</div>';
                        
                        $store_to_location_mapping = StoreLocationMapping::where('suburb_id','=',$hold_values->to_location)->first(); 
                        
                        $transform_set = array(
                                                'from_suburb' => $store_location_mapping->store_name,
                                                'to_suburb' => $store_to_location_mapping->store_name,
                                                'Product_Details' => $html,
                                            );      

                        $subject = "New Transfer Request to ".$store_to_location_mapping->store_name;
                        $content = static::_get_template('holdstock_store_transfer_request');

                        static::_transform($transform_set, $content);
                        static::_mail($subject, $content, $store_location_mapping->email);

                }
        }
        
        /* email to customer  when transfer cancelled */
        public static function emailTransferCancelledHoldStock($holdstocks, $customer)
        {
            
                //Get Receiveing Store Details
                $tostorelocation = StoreLocationMapping::where('suburb_id','=',$holdstocks->to_location)->where('store_id','=',Auth::user()->store_id)->first();
                //Get Sending Store Details
                $fromstorelocation = StoreLocationMapping::where('suburb_id','=',$holdstocks->from_location)->where('store_id','=',Auth::user()->store_id)->first();
                
                $html = '<div>';
                            $html .='<table style="width: 100%; color: #737373;  border: 1px solid gray;">';
                                    $html .='<tr style="background: #83A9EA; color: white;">';
                                            $html .='<th style="width: 40%;">Product Name</th>';
                                            $html .='<th style="width: 40%;">Sku Number</th>';
                                            $html .='<th style="width: 20%;">Quantity</th>';
                                    $html .='</tr>';
                                    $html .='<tr>';
                                            $html .='<td>'.$holdstocks->product_name.'</td>';
                                            $html .='<td>'.$holdstocks->sku_number.'</td>';
                                            $html .='<td>'.$holdstocks->quantity.'</td>';
                                    $html .='</tr>';
                            $html .='</table>';
                $html .='</div>';

                $transform_set = array(
                                                        'first_name' => $customer->name,
                                                        'Product_Details' => $html,
                                                        'to_store'=>$tostorelocation->store_name,
                                                );

                $subject = "Your Product Hold at I Love Ugly ".$tostorelocation->store_name." has been Cancelled";
                $content = static::_get_template('holdstock_transfer_cancelled');
                static::_transform($transform_set, $content);

                static::_mail($subject, $content, $customer->email);     
        }
        
        /* email to customer  when transfer not fullfilled */
        public static function emailTransferNotFullfilledHoldStock($holdstocks, $customer)
        {
            
                //Get Receiveing Store Details
                $tostorelocation = StoreLocationMapping::where('suburb_id','=',$holdstocks->to_location)->where('store_id','=',Auth::user()->store_id)->first();
                //Get Sending Store Details
                $fromstorelocation = StoreLocationMapping::where('suburb_id','=',$holdstocks->from_location)->where('store_id','=',Auth::user()->store_id)->first();
                
                $html = '<div>';
                        $html .='<table style="width: 100%; color: #737373;  border: 1px solid gray;">';
                                $html .='<tr style="background: #83A9EA; color: white;">';
                                        $html .='<th style="width: 40%;">Product Name</th>';
                                        $html .='<th style="width: 40%;">Sku Number</th>';
                                        $html .='<th style="width: 20%;">Quantity</th>';
                                $html .='</tr>';
                                $html .='<tr>';
                                        $html .='<td>'.$holdstocks->product_name.'</td>';
                                        $html .='<td>'.$holdstocks->sku_number.'</td>';
                                        $html .='<td>'.$holdstocks->quantity.'</td>';                
                                $html .='</tr>';
                        $html .='</table>';
                $html .='</div>';

                $transform_set = array(
                                                            'first_name' => $customer->name,
                                                            'Product_Details' => $html,
                                                            'from_store'=>$fromstorelocation->store_name,
                                                    );

                $subject = "I Love Ugly Product Transfer Not Fulfilled";
                $content = static::_get_template('holdstock_transfer_not_fulfilled');
                
                static::_transform($transform_set, $content);
                static::_mail($subject, $content, $customer->email);     
        }
        
        /* email to store  when transfer not fullfilled */
        public static function emailTransferNotFullfilledStoreHoldStock($holdstocks)
        {
            
                //Get Receiveing Store Details
                $tostorelocation = StoreLocationMapping::where('suburb_id','=',$holdstocks->to_location)->where('store_id','=',Auth::user()->store_id)->first();
                //Get Sending Store Details
                $fromstorelocation = StoreLocationMapping::where('suburb_id','=',$holdstocks->from_location)->where('store_id','=',Auth::user()->store_id)->first();
                
                $html = '<div>';
                        $html .='<table style="width: 100%; color: #737373;  border: 1px solid gray;">';
                                $html .='<tr style="background: #83A9EA; color: white;">';
                                        $html .='<th style="width: 40%;">Product Name</th>';
                                        $html .='<th style="width: 40%;">Sku Number</th>';
                                        $html .='<th style="width: 20%;">Quantity</th>';
                                $html .='</tr>';
                                $html .='<tr>';
                                        $html .='<td>'.$holdstocks->product_name.'</td>';
                                        $html .='<td>'.$holdstocks->sku_number.'</td>';
                                        $html .='<td>'.$holdstocks->quantity.'</td>';
                                $html .='</tr>';
                
                         $html .='</table>';
                $html .='</div>';
                
                $transform_set = array(
                    'name' => 'There',
                    'product_name' => $html,
                    'date'=>date('d-m-Y'),
                                      );

                $subject = "Transfer Not Fulfilled";
                $content = static::_get_template('transfer_not_fulfilled_store');
                static::_transform($transform_set, $content);

                static::_mail($subject, $content, $fromstorelocation->email);     
        }
        
        /* outgoing email sent to customer when transfer sent(In Transit)*/
        public static function emailTransferSendCustomer($holdstocks, $customer)
        {
  
                  //Get Receiveing Store Details
                $tostorelocation = StoreLocationMapping::where('suburb_id','=',$holdstocks->to_location)->where('store_id','=',Auth::user()->store_id)->first();
                //Get Sending Store Details
                $fromstorelocation = StoreLocationMapping::where('suburb_id','=',$holdstocks->from_location)->where('store_id','=',Auth::user()->store_id)->first();
                     
                $html = '<div>';
                        $html .='<table style="width: 100%; color: #737373;  border: 1px solid gray;">';
                                $html .='<tr style="background: #83A9EA; color: white;">';
                                        $html .='<th style="width: 40%;">Product Name</th>';
                                        $html .='<th style="width: 40%;">Sku Number</th>';
                                        $html .='<th style="width: 20%;">Quantity</th>';
                                $html .='</tr>';
                                $html .='<tr>';
                                        $html .='<td>'.$holdstocks->product_name.'</td>';
                                        $html .='<td>'.$holdstocks->sku_number.'</td>';
                                        $html .='<td>'.$holdstocks->quantity.'</td>';
                                $html .='</tr>';
                        $html .='</table>';
                $html .='</div>';

                $transform_set = array(
                                                        'first_name' => $customer->name,
                                                        'Product_Details' => $html,
                                                        'to_store'=> $tostorelocation->store_name,
                                                        'from_store'=> $fromstorelocation->store_name,
                                                        'extend_link' => URL::route('postCancelHoldById', $holdstocks->id),
                                                        'cancel_link' => URL::route('postExtendedHoldById', $holdstocks->id),
                                                 );

                $subject = "Your Transfer is Now In Transit to I Love Ugly ".$tostorelocation->store_name;
                $content = static::_get_template('holdstock_transfer_sent');
                
                static::_transform($transform_set, $content);
                static::_mail($subject, $content, $customer->email);   
        }
        
        /* outgoing email sent to store when transfer sent(In Transit)*/
         public static function emailTransferSendToStore($holdstocks)
        {
                //Get Receiveing Store Details
                $tostorelocation = StoreLocationMapping::where('suburb_id','=',$holdstocks->to_location)->where('store_id','=',Auth::user()->store_id)->first();
                //Get Sending Store Details
                $fromstorelocation = StoreLocationMapping::where('suburb_id','=',$holdstocks->from_location)->where('store_id','=',Auth::user()->store_id)->first();
                        
                $html = '<div>';
                        $html .='<table style="width: 100%; color: #737373;  border: 1px solid gray;">';
                                $html .='<tr style="background: #83A9EA; color: white;">';
                                        $html .='<th style="width: 40%;">Product Name</th>';
                                        $html .='<th style="width: 40%;">Sku Number</th>';
                                        $html .='<th style="width: 20%;">Quantity</th>';
                                $html .='</tr>';
                                $html .='<tr>';
                                        $html .='<td>'.$holdstocks->product_name.'</td>';
                                        $html .='<td>'.$holdstocks->sku_number.'</td>';
                                        $html .='<td>'.$holdstocks->quantity.'</td>';
                                  $html .='</tr>';

                        $html .='</table>';
                $html .='</div>';

                $transform_set = array(
                                                        'Product_Details' => $html,
                                                        'to_store'=> $tostorelocation->store_name,
                                                        'from_store'=> $fromstorelocation->store_name,
                                                );

                $subject = "Your Request Is Now In Transit to ".$tostorelocation->store_name;
                $content = static::_get_template('holdstock_transfer_sent_store');

                static::_transform($transform_set, $content);
                static::_mail($subject, $content, $tostorelocation->email);   
        }
        
        
        /* Holdstock lost interst mail send to customer */
        
        public static function emailHoldToCustomerLostInterest($holdstocks, $customer)
        {
            
            //var_dump($holdstocks);exit();
         $html = '<div>';
            $html .='<table>';
            $html .='<tr>';
            $html .='<th>Product Name</th>';
            $html .='<th>Sku Number</th>';
            $html .='<th>Quantity</th>';
            
            $html .='</tr>';
          
               // $storelocation = StoreLocationMapping::where('suburb_id','=',$holdstocks->to_location)->first();
                // $store_name  = Suburb::where('id','=',$storelocation->suburb_id)->first(); 
                 //var_dump($store_name->suburb);exit();
                    $html .='<tr>';
                    $html .='<td>'.$holdstocks->product_name.'</td>';
                    $html .='<td>'.$holdstocks->sku_number.'</td>';
                    $html .='<td>'.$holdstocks->quantity.'</td>';
                   
                    $html .='</tr>';
                
            $html .='</table>';
            $html .='</div>';
            
           
                       
            $transform_set = array(
                'name' => $customer->name,
                'product_name' => $html,
                
                                  );
            
            $subject = "Holdstock Lost Interest";
            $content = static::_get_template('hold_lost_interest');
            static::_transform($transform_set, $content);
            
            static::_mail($subject, $content, $customer->email);   
        }
        
        /* Holdstock  purchased mail send to customer */
        
         public static function emailHoldToCustomerPurchased($holdstocks, $customer)
        {
         $html = '<div>';
            $html .='<table>';
            $html .='<tr>';
            $html .='<th>Product Name</th>';
            $html .='<th>Sku Number</th>';
            $html .='<th>Quantity</th>';
            
            $html .='</tr>';
          
                $storelocation = StoreLocationMapping::where('suburb_id','=',$holdstocks->to_location)->first();
                 $store_name  = Suburb::where('id','=',$storelocation->suburb_id)->first(); 
                 //var_dump($store_name->suburb);exit();
                    $html .='<tr>';
                    $html .='<td>'.$holdstocks->product_name.'</td>';
                    $html .='<td>'.$holdstocks->sku_number.'</td>';
                    $html .='<td>'.$holdstocks->quantity.'</td>';
                   
                    $html .='</tr>';
                
            $html .='</table>';
            $html .='</div>';
            
           
                       
            $transform_set = array(
                'name' => $customer->name,
                'product_name' => $html,
                'suburb'=> $store_name->suburb,
                                  );
            
            $subject = "Holdstock purchased";
            $content = static::_get_template('hold_purchased');
            static::_transform($transform_set, $content);
            
            static::_mail($subject, $content, $customer->email);   
        }
        
         /* Holdstock  Extended mail send to customer */
        
         public static function emailHoldToCustomerExtended($holdstocks, $customer)
        {
         $html = '<div>';
            $html .='<table>';
            $html .='<tr>';
            $html .='<th>Product Name</th>';
            $html .='<th>Sku Number</th>';
            $html .='<th>Quantity</th>';
            
            $html .='</tr>';
          
                $storelocation = StoreLocationMapping::where('suburb_id','=',$holdstocks->to_location)->first();
                 $store_name  = Suburb::where('id','=',$storelocation->suburb_id)->first(); 
                 //var_dump($store_name->suburb);exit();
                    $html .='<tr>';
                    $html .='<td>'.$holdstocks->product_name.'</td>';
                    $html .='<td>'.$holdstocks->sku_number.'</td>';
                    $html .='<td>'.$holdstocks->quantity.'</td>';
                   
                    $html .='</tr>';
                
            $html .='</table>';
            $html .='</div>';
            
           
                       
            $transform_set = array(
                'name' => $customer->name,
                'product_name' => $html,
                'suburb'=> $store_name->suburb,
                                  );
            
            $subject = "Holdstock order extended";
            $content = static::_get_template('hold_extended');
            static::_transform($transform_set, $content);
            
            static::_mail($subject, $content, $customer->email);   
        }
        
        /* Holdstock  return to stock mail send to customer */
        
         public static function emailHoldToCustomerReturnStock($holdstocks, $customer)
        {
         $html = '<div>';
            $html .='<table>';
            $html .='<tr>';
            $html .='<th>Product Name</th>';
            $html .='<th>Sku Number</th>';
            $html .='<th>Quantity</th>';
            
            $html .='</tr>';
          
                $storelocation = StoreLocationMapping::where('suburb_id','=',$holdstocks->to_location)->first();
                $store_name  = Suburb::where('id','=',$storelocation->suburb_id)->first(); 
                 //var_dump($store_name->suburb);exit();
                    $html .='<tr>';
                    $html .='<td>'.$holdstocks->product_name.'</td>';
                    $html .='<td>'.$holdstocks->sku_number.'</td>';
                    $html .='<td>'.$holdstocks->quantity.'</td>';
                   
                    $html .='</tr>';
                
            $html .='</table>';
            $html .='</div>';
            
           
                       
            $transform_set = array(
                'name' => $customer->name,
                'product_name' => $html,
                'suburb'=> $store_name->suburb,
                                  );
            
            $subject = "Holdstock Returned to stock";
            $content = static::_get_template('hold_returned_stock');
            static::_transform($transform_set, $content);
            
            static::_mail($subject, $content, $customer->email);   
        }
        
        
        public static function scheduler_holdstock_expiring_email_to_customer($holdstocks, $customer)
        {
            $html = '<div>';
            $html .='<table>';
            $html .='<tr>';
            $html .='<td>Product Name</td>';
            $html .='<td>From Location</td>';
            $html .='<td>To Location</td>';
            $html .='<td>Expiring on</td>';
            $html .='<td>Quantity</td>';
            
            $html .='</tr>';
            $html .='</table>';
            $html .='<table>';
                foreach($holdstocks as $holdstock)
                {
                    $from_location = Suburb::where('id','=',$holdstock->from_location)->first();
                    $to_location = Suburb::where('id','=',$holdstock->to_location)->first();
                    $holdstocks   = HoldStock::where('id','=',$holdstock->hold_stock_id)->first();
                    $maintain_request   = MaintainRequest::where('hold_stock_mapping_id','=',$holdstock->id)->first();
                    
                    $html .='<tr>';
                    $html .='<td>'.$holdstock->product_name.'</td>';
                    $html .='<td>'.$from_location->suburb.'</td>';
                    $html .='<td>'.$to_location->suburb.'</td>';
                    $html .='<td>'.$maintain_request->date.'</td>';
                    $html .='<td>'.$holdstock->quantity.'</td>';
                    $html .='</tr>';
                }
            $html .='</table>';
            $html .='</div>';
            
           
                       
            $transform_set = array(
                'name' => $customer->name,
                'date' => date('Y-m-d'),
                'extend' => '#',
                'cancel' => '#',
                'product' => $html,
               
                                  );
            
            $subject = "Your requested item(s) going to be expired soon";
            $content = static::_get_template('hold_stock_expiring_mail_to_customer');
            static::_transform($transform_set, $content);
            
            static::_mail($subject, $content, $customer->email);     
        }
        
        public static function scheduler_holdstock_expired_email_to_customer($holdstocks, $customer)
        {
                $html = '<div>';
                $html .='<table>';
                $html .='<tr>';
                $html .='<td>Product Name</td>';
                $html .='<td>From Location</td>';
                $html .='<td>To Location</td>';
                $html .='<td>Expired on</td>';
                $html .='<td>Quantity</td>';

                $html .='</tr>';
                $html .='</table>';
                $html .='<table>';
                        foreach($holdstocks as $holdstock)
                        {
                            $from_location = Suburb::where('id','=',$holdstock->from_location)->first();
                            $to_location = Suburb::where('id','=',$holdstock->to_location)->first();
                            $holdstocks   = HoldStock::where('id','=',$holdstock->hold_stock_id)->first();
                            $maintain_request   = MaintainRequest::where('hold_stock_mapping_id','=',$holdstock->id)->first();

                            $html .='<tr>';
                            $html .='<td>'.$holdstock->product_name.'</td>';
                            $html .='<td>'.$from_location->suburb.'</td>';
                            $html .='<td>'.$to_location->suburb.'</td>';
                            $html .='<td>'.$maintain_request->date.'</td>';
                            $html .='<td>'.$holdstock->quantity.'</td>';
                            $html .='</tr>';
                        }
                $html .='</table>';
                $html .='</div>';



                $transform_set = array(
                        'name' => $customer->name,
                        'date' => date('Y-m-d'),
                        'extend' => '#',
                        'product' => $html,

                        );

                $subject = "Your requested item(s) have expired";
                $content = static::_get_template('hold_stock_expired_mail_to_customer');
                static::_transform($transform_set, $content);

                static::_mail($subject, $content, $customer->email);     
        }
        
         public static function received_stock_email_to_customer($holdstock ,$customer)
        {
                $html = '<div>';
                $html .='<table>';
                $html .='<tr>';
                $html .='<td>Product Name</td>';
                $html .='<td>From Location</td>';
                $html .='<td>To Location</td>';
                $html .='<td>Hold For</td>';
                $html .='<td>Quantity</td>';
                $html .='<td>Expairy Date</td>';

                $html .='</tr>';
                $html .='</table>';
                $html .='<table>';

                $from_location = Suburb::where('id','=',$holdstock->from_location)->first();
                $to_location = Suburb::where('id','=',$holdstock->to_location)->first();
                $holdstocks   = \App\Http\Models\MaintainRequest::where('hold_stock_mapping_id','=',$holdstock->id)->first();

                $html .='<tr>';
                $html .='<td>'.$holdstock->product_name.'</td>';
                $html .='<td>'.$from_location->suburb.'</td>';
                $html .='<td>'.$to_location->suburb.'</td>';
                $html .='<td>'.$holdstocks->countdown_duration.'days'.'</td>';
                $html .='<td>'.$holdstock->quantity.'</td>';
                $html .='<td>'.$holdstocks->date.'</td>';
                $html .='</tr>';

                $html .='</table>';
                $html .='</div>';
            
                $cancel = 'url';
                       
                $transform_set = array(
                        'name' => $customer->name,
                        'product_name' => $html,
                        );

                $subject = $holdstock->product_name." now available to collect at ".$to_location->suburb;
                $content = static::_get_template('received_product_to_store');
                static::_transform($transform_set, $content);

                static::_mail($subject, $content, $customer->email);     
        }
        
        
         public static function request_email_to_customer($wishlists ,$customer)
        {
                $store_id = $customer->store_id;
                $store_name = Store::where('id','=',$store_id)->first();
                $store = $store_name->store_name;
                $website = "https://www.iloveugly.co.nz/";
                
                                
                 $html = '<div>';
                        $html .='<table style="width: 100%; color: #737373;  border: 1px solid gray;">';
                                $html .='<tr style="background: #83A9EA; color: white;">';
                                        $html .='<th style="width: 30%;">Product Type</th>';
                                        $html .='<th style="width: 20%;">Size</th>';
                                        $html .='<th style="width: 20%;">Color</th>';
                                        $html .='<th style="width: 10%;">Date</th>';
                                $html .='</tr>';
                
               

                        $product_type =$wishlists->product_type_id;
                        $product_type = ProductType::where('id','=',$product_type)->first();
                        $size  = $wishlists->size_id;
                        $color = $wishlists->color_id;
                       if($size!=0)
                       {  

                            $size  = Size::where('id','=',$size)->first();
                            $size_name = $size->size_name;
                       }
                        else
                        {
                            $size_name = 'N/A';
                        }

                        if($color!=0)
                       {  

                            $color  = Color::where('id','=',$color)->first();
                            $color_name = $color->color;
                       }
                        else
                        {
                                $color_name = 'N/A';
                        }
                        
                        

                        $html .='<tr>';
                        $html .='<td>'.$product_type->product_type_name.'</td>';
                        $html .='<td>'.$size_name.'</td>';
                        $html .='<td>'.$color_name.'</td>';
                        $html .='<td>'.$wishlists->created_at.'</td>';

                        $html .='</tr>';

                $html .='</table>';
            $html .='</div>';

                    
                
                $transform_set = array(
                    'first_name' => $customer->first_name,
                    'product_details' => $html,
                    'store'=>$store,
                    'view_link' => URL::route('requestProductCustomerEmail', $wishlists->id),
                    'website'=>$website,
                                      );

                $subject = "I Love Ugly Product Request Submitted";
                $content = static::_get_template('whistlist_requested');
                static::_transform($transform_set, $content);

                static::_mail($subject, $content, $customer->email);     
        }
        
        /* restock request to customer */
          public static function requestRestockEmailCustomer($wishlists ,$customer)
        {
                $store_id = $customer->store_id;
                $store_name = Store::where('id','=',$store_id)->first();
                $store = $store_name->store_name;
                $website = "https://www.iloveugly.co.nz/";
                
                
                $html = '<div>';
                        $html .='<table style="width: 100%; color: #737373;  border: 1px solid gray;">';
                                $html .='<tr style="background: #83A9EA; color: white;">';
                                        $html .='<th style="width: 30%;">Product Name</th>';
                                        $html .='<th style="width: 20%;">Sku Number</th>';
                                        $html .='<th style="width: 20%;">Quantity</th>';
                                        $html .='<th style="width: 10%;">Date</th>';
                                $html .='</tr>';
                
                

                $product_id = $wishlists->product_location_mapping_id;
                $product_mapping = ProductLocationMapping::where('id','=',$product_id)->first();
                       

                        $html .='<tr>';
                        $html .='<td>'.$product_mapping->Description.'</td>';
                        $html .='<td>'.$product_mapping->sku_number.'</td>';
                        $html .='<td>'.$wishlists->quantity.'</td>';
                        $html .='<td>'.$wishlists->created_at.'</td>';
                        $html .='</tr>';

                $html .='</table>';
                $html .='</div>';



                $transform_set = array(
                    'first_name' => $customer->first_name,
                    'product_details' => $html,
                    'store'=>$store,
                     'view_link' => URL::route('requestProductCustomerEmail', $wishlists->id),
                    'website'=>$website,
                    );
               

                $subject = "I Love Ugly Product Restock Request Submitted";
                $content = static::_get_template('whistlist_restock_requested');
                static::_transform($transform_set, $content);

                static::_mail($subject, $content, $customer->email);     
        }
        
         public static function  whistlist_email_expired_customer($wishlists_check ,$customer)
        {
             
             
                $status = Config('default.whishlist_reverse.'.$wishlists_check->status);

                $transform_set = array(
                                                'name' => $customer->name,
                                                'link' => URL::route('requestWishlistChangeStatus', $wishlists_check->id),
                                                );

                $subject = "Change your status  $status";
                
                $content = static::_get_template('whishlist_expired');
                
                static::_transform($transform_set, $content);
                static::_mail($subject, $content, $customer->email);
        }
        
         public static function  whistlist_email_expiring_customer($wishlists_check ,$customer)
        {
                         
                $status = Config('default.whishlist_reverse.'.$wishlists_check->status);
            
                $transform_set = array(
                                                'first_name' => $customer->first_name,
                                                'product_details'=>$html,
                                                'extend_link' => URL::route('requestWishlistExtend', $wishlists_check->id),
                                                'cancel_link' => URL::route('requestWishlistCancel', $wishlists_check->id),
                                                );

                $subject = "I Love Ugly Product Request Expiring";
                
                $content = static::_get_template('whishlist_expiring');
                
                static::_transform($transform_set, $content);
                static::_mail($subject, $content, $customer->email);
        }
        
}   
