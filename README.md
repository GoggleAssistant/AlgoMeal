

### 1. Install & Start XAMPP
*   Download and install [XAMPP](https://www.apachefriends.org/index.html).
*   Open the **XAMPP Control Panel** and **Start** both **Apache** and **MySQL**.

### 2. Place the Project Files
*   Copy the `AlgoMeal` folder into the `htdocs` directory of your XAMPP installation.
*   The path should look like: `C:\xampp\htdocs\AlgoMeal`

### 3. Create the Database
*   Open your browser and go to `http://localhost/phpmyadmin/`.
*   Click on **New** in the left sidebar.
*   Enter `algomeal_db` as the database name and click **Create**.

### 4. Import the SQL Schema
*   While `algomeal_db` is selected in phpMyAdmin, click the **Import** tab at the top.
*   Click **Choose File** and select `c:\xampp\htdocs\AlgoMeal\database_setup.sql`.
*   Scroll to the bottom and click **Import** (or **Go**).

> [!TIP]
> **Command Line Alternative:**
> If you prefer the terminal, you can run:
> ```powershell
> C:\xampp\mysql\bin\mysql.exe -u root algomeal_db < C:\xampp\htdocs\AlgoMeal\database_setup.sql
> ```

### 5. Configure Database Connection
*   Open `c:\xampp\htdocs\AlgoMeal\db.php`.
*   Ensure the `$db_user`, `$db_pass`, and `$db_name` match your local settings. By default on XAMPP, it should be:
    ```php
    $db_host = "localhost";
    $db_user = "root";
    $db_pass = "";
    $db_name = "algomeal_db";
    ```

### 6. Access the App
*   Open your browser and navigate to: `http://localhost/AlgoMeal/`

You should now be able to log in and use the application!
