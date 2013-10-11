CI_AuthLTE
==========
Beta version 0.1.0 has been released. 

CI_AuthLTE is a light authentication library for codeigniter 2.x.


Dependencies
============
[PHP 5.3](http://php.net) or higher

[Codeigniter 2.x](http://ellislab.com/codeigniter) or higher

[MySQL database](http://www.mysql.com)

Main Features
=============
 1- PHPass for password encryption
 
 2- Limited login attempts
 
 3- Secured "remember me" with limited time period
 
 4- Forgetton password help
 
 5- Optional activation email
 
 6- Groups and priviledges control
 
 7- Simple database structure

Quick Start
=============
**Installation:**

Add the files to the corresponding directories. Then, dump the sql file to your database.
Open the auth_model.php file which is located in the Application/models directory. Make sure to edit the settings to match your desire.

To initialize the library, load the model using Codeigniter's load function in your controller:
```PHP
$this->load->model('auth_model');
```

**Creating an account:**

To create an account simply use the create_account() function. Example:
```PHP
$user_id = $this->auth_model->create_account($email, $password);
```
You can add customized columns to the accounts table such as phone and name. Then you can use the 4th parameter of the create_account() function to add custom data to your table. Example:
```PHP
$custom_data = array(
					"name"  => "Foo Bar",
					"phone" => "555-555-5555"
				);
$user_id = $this->auth_model->create_account($email, $password, NULL, $custom_data);
```
If you want the account to be connected to a group, add the group id as a 3rd paramater.

**Logging In:**

Log a user in using the login() function. Example:
```PHP
$this->auth_model->login($email, $password, $remember_me = FALSE);
```
The function will return (bool) TRUE on success. False otherwise.

**Checking If the User is Logged in**
```PHP
var_dump($this->auth_model->is_logged_in());
```

**Get Current User ID**
```PHP
$user_id = $this->auth_model->user_id();
```

**Getting and Setting Error and Status Messages**

The error and status messages are stored in the language file included with the library. 
To set a customized message, add your message to the language file.
For example:
```PHP
$lang['auth.custom_error'] = 'The fields %s and %s are invalid!';
$lang['auth.email_field'] = 'Email';
$lang['auth.password_field'] = 'Password';
```
Then use the set_error_message() or set_status_message() by passing the $lang key to the function.
The second param is optional and used to pass data to the sprintf function. For example:
```PHP
$this->auth_model->set_error_message('auth.custom_error');
//Or using the second param
$this->auth_model->set_error_message('auth.custom_error', lang('auth.email_field'));
//You can also pass an array as a second paramater.
$sprintf = array(
				lang('auth.email_field'),
				lang('auth.password_field')
			);
$this->auth_model->set_error_message('auth.custom_error', $sprintf);
//Similarly, you can use the set_status_messages()
```

To retrieve a message, use error_messages() or status_messages(). For example:
```PHP
//You can choose the opening and closing tags for each message (line)
//Returned value: <p>first error</p><p>second error</p> ... and so on
$errors = $this->auth_model->error_messages('<p>', '</p>');
```

For a full list of the library's functions, download the *CI_AuthLTE_documentation* folder and open the index.html file.