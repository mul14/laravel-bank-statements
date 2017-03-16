# Bank Statements Collector
Laravel package to collect your bank statements history. Currently support for parsing statements history from **BCA** and **Mandiri** e-banking websites.

**BNI** e-banking website is also supported, but I'm not provide the class in this package. Additional open-source libraries/softwares is required inside the server to help solving the captcha, also additional process in your main app are required. **No additional third-party service payment** required.

## Requirements
Check the [composer.json](https://github.com/feelinc/laravel-bank-statements/blob/master/composer.json) file
    
## Installation
    $ php composer.phar require sule/bank-statements

After you have installed package, open your Laravel config file **config/app.php** and add the following lines.

In the **$providers** array add the service providers for this package.

    Sule\BankStatements\Provider\LaravelServiceProvider::class
    
Create migrations

    $ php artisan bank-statements:accounts-table
    $ php artisan bank-statements:table
    
Do migration

    $ php artisan migrate
    
## Code Examples

Each collector require a bank account data created. Create one first.

    $accountProvider = app(\Sule\BankStatements\Account::class);
    
    $data = [
        'title'         => 'BCA',
        'url'           => 'https://ibank.klikbca.com',
        'collector'     => 'bca',
        'name'          => 'Your name',
        'user_id'       => 'yourloginuserid',
        'password'      => encrypt('yourloginpassword'),
        'last_activity' => time(),
        'created_at'    => new DateTime(),
        'updated_at'    => new DateTime()
    ];
    
    $accountProvider->create($data);
    
To start collecting

    // I think 6 days range is good enough
    $startOfMonth = (Carbon::now())->startOfMonth();
    $startDate    = (Carbon::now())->subDays(6);
    $endDate      = Carbon::now();

    // Some e-banking websites does not allow us to collect more than a month
    // from current date
    // Use first date of current month if 6 days are to much
    if ($startOfMonth->month != $startDate->month || $startOfMonth->year != $startDate->year) {
        $startDate = $startOfMonth;
    }

    $statementProvider = app(\Sule\BankStatements\Statement::class);
    $statementProvider->collect($startDate, $endDate);
    
The whole collected statements history will be saved into a table, you can collect them using below codes example

    // Set specific bank account ID if required
    $accountId = 0;
    
    // Set specific statement type "CR" or "DB"
    $type = '';
    
    // Set specific date range
    $fromDate = '';
    $toDate   = '';
    
    $statementProvider = app(\Sule\BankStatements\Statement::class);
    $collection = $statementProvider->search([
        'bank_account_id' => $accountId, 
        'type'            => $type, 
        'from_date'       => $fromDate, 
        'end_date'        => $toDate, 
        'order_by'        => 'id'
    ]);
    
    // Then do whatever you want with the collection result

## NOTES

**BCA** e-banking website does not provide a correct date for new statement (today), that will be updated by them in the next day.

Each statement data will be having a unique ID when stored in table, to make sure does not re-created. Regarding **BCA** statement data, the date will be updated if found in your next collecting process. You can find the data used to create the unique ID in each collector class.

Don't login to e-banking websites manually if you going to use the collector process. All e-banking websites does not allow multiple login attempt.

Make sure the user ID and password provided are correct, because all e-banking websites will block your account if failed three times. If you failed twice, do a success login manually first, then try again the collector process.

Don't execute via cron schedule. Just do it when you really need. Because in case you does not know if previous process is really done (logged out from the e-banking website) or maybe your account will be blocked for suspecious activities.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

## Configuration
Most configuration can be overrided in your **.env** file. All of them having default value.

    BANK_CLIENT_USER_AGENT
        Web browser user agent going to use for CURL client.
    BANK_CLIENT_IP_ADDRESS
        IP Address going to use for CURL client.
    BANK_CLIENT_REQUEST_DELAY
        Time delay for each CURL request.
    BANK_CLIENT_TIMEOUT
        CURL request timeout.
    BANK_CLIENT_DEBUG
        CURL request debug.
    BANK_COLLECTOR
        Collector type, currently only support for web parser.
    BANK_TEMP_STORAGE_PATH
        Temporary storage path.
    DB_CONNECTION
        Database connection going to use.
    BANK_ACCOUNTS_TABLE
        Database table to store bank accounts information.
    BANK_STATEMENTS_TABLE
        Database table to store bank statements history.
        
To modify the configuration file, the default config file need to be published first. You can find it later in "**config/sule/bank-statements.php**"

    $ php artisan vendor:publish --provider="Sule\BankStatements\Provider\LaravelServiceProvider"

## Contributing

Contributions to the Laravel Bank Statements library are welcome. Please note the following guidelines before submiting your pull request.

- Follow [PSR-2](http://www.php-fig.org/psr/psr-2/) coding standards.
- Write tests for new functions and added features

## License

Intervention Image is licensed under the [MIT License](http://opensource.org/licenses/MIT).

Copyright 2017 [Sulaeman](https://www.sulaeman.com/)