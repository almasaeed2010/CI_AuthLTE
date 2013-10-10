CI_AuthLTE
==========
Beta version 0.1.0 has been released. 

CI_AuthLTE is a light authentication library for codeigniter 2.x.


Dependencies
============
PHP 5.3 or higher
Codeigniter 2.x or higher
MySQL database

Main Features
=============
 1- PHPass for password encryption
 2- Limited login attempts
 3- Secured "remember me" with limited time period
 4- Forgetton password help
 5- Optional activation email
 6- Groups and priviledges control
 7- Simple database structure

Documentation
=============
Installation:
Add the files to the corresponding directories. Then, dump the sql file to your database.
Open the auth_model.php file which is located in the Application/models directory. Make sure to edit the settings to match your desire.

Load the model using Codeigniter's load function:
```PHP
$this->load->model('auth_model');
```

Creating an account:
To create an account simply use the create_account() function. Example:
```PHP
$user_id = $this->auth_model->create_account($email, $password);
```
You can add customized data to the accounts table such as phone and name. Then you can use the 4th parameter of the create_account() function to add custom data to your table. Example:
```PHP
$custom_data = array(
					"name"  => "Foo Bar",
					"phone" => "555-555-5555'
				);
$user_id = $this->auth_model->create_account($email, $password, NULL, $custom_data);
```
If you want the account to be connected to a group, add the group id as a 3rd paramater.

Logging In:
Log a user in using the login() function. Example:
```PHP
$this->auth_model->login($email, $password, $remember_me = FALSE);
```
The function will return (bool) TRUE on success. False otherwise.