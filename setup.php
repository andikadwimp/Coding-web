<?php
require_once 'includes/config.php';
header('Content-Type: text/html; charset=UTF-8');
echo "<h2>Database Setup</h2><pre>";

$queries = [
    // Core tables
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        bank VARCHAR(50) DEFAULT '',
        acc_name VARCHAR(100) DEFAULT '',
        acc_num VARCHAR(50) DEFAULT '',
        balance BIGINT DEFAULT 0,
        total_deposit BIGINT DEFAULT 0,
        total_turnover BIGINT DEFAULT 0,
        ref_code VARCHAR(10),
        referred_by VARCHAR(10),
        display_id VARCHAR(20),
        display_name VARCHAR(50),
        avatar VARCHAR(10) DEFAULT 'm1',
        vip_level INT DEFAULT 0,
        role VARCHAR(10) DEFAULT 'user',
        status VARCHAR(10) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(30),
        amount BIGINT DEFAULT 0,
        balance_before BIGINT DEFAULT 0,
        balance_after BIGINT DEFAULT 0,
        ref_id VARCHAR(50),
        note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS deposits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        tx_id VARCHAR(50) UNIQUE,
        method VARCHAR(30),
        type VARCHAR(30),
        nominal BIGINT DEFAULT 0,
        pay_amount BIGINT DEFAULT 0,
        bonus_id INT,
        bonus_amount BIGINT DEFAULT 0,
        pay_url TEXT,
        pay_data TEXT,
        status VARCHAR(20) DEFAULT 'pending',
        turnover_at_deposit BIGINT DEFAULT 0,
        turnover_met TINYINT DEFAULT 0,
        expires_at DATETIME,
        paid_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS withdrawals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount BIGINT DEFAULT 0,
        bank VARCHAR(50),
        acc_name VARCHAR(100),
        acc_num VARCHAR(50),
        status VARCHAR(20) DEFAULT 'pending',
        note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        `key` VARCHAR(100) UNIQUE NOT NULL,
        `value` TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS providers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(100),
        logo TEXT,
        sort_order INT DEFAULT 0,
        status TINYINT DEFAULT 1,
        game_count INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS games (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider_code VARCHAR(50),
        game_code VARCHAR(100),
        game_name VARCHAR(200),
        game_type VARCHAR(50),
        banner TEXT,
        status TINYINT DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_game (provider_code, game_code)
    )",
    "CREATE TABLE IF NOT EXISTS memos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(20) DEFAULT 'broadcast',
        to_user_id INT,
        title VARCHAR(200),
        body TEXT,
        is_read TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS memo_reads (
        memo_id INT NOT NULL,
        user_id INT NOT NULL,
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (memo_id, user_id)
    )",
    "CREATE TABLE IF NOT EXISTS bonuses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        percentage INT DEFAULT 0,
        max_amount BIGINT DEFAULT 0,
        turnover_x INT DEFAULT 1,
        min_deposit BIGINT DEFAULT 0,
        status VARCHAR(10) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS redeem_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) UNIQUE NOT NULL,
        amount BIGINT DEFAULT 0,
        max_uses INT DEFAULT 0,
        used_count INT DEFAULT 0,
        status VARCHAR(10) DEFAULT 'active',
        expires_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS redeem_usage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code_id INT,
        user_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS vip_claims (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        vip_level INT NOT NULL,
        claim_type VARCHAR(20) NOT NULL,
        period VARCHAR(20) NOT NULL,
        amount BIGINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_claim (user_id, claim_type, period)
    )",
    "CREATE TABLE IF NOT EXISTS rebates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        week_start DATE NOT NULL,
        week_end DATE NOT NULL,
        total_bet BIGINT DEFAULT 0,
        rebate_pct DECIMAL(5,2) DEFAULT 0,
        rebate_amount BIGINT DEFAULT 0,
        status VARCHAR(20) DEFAULT 'pending',
        paid_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_rebate (user_id, week_start)
    )",
    // ALTER TABLE for missing columns
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS display_id VARCHAR(20)",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS display_name VARCHAR(50)",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar VARCHAR(10) DEFAULT 'm1'",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS vip_level INT DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(10) DEFAULT 'active'",
    "ALTER TABLE deposits ADD COLUMN IF NOT EXISTS turnover_at_deposit BIGINT DEFAULT 0",
    "ALTER TABLE deposits ADD COLUMN IF NOT EXISTS turnover_met TINYINT DEFAULT 0",
    "ALTER TABLE deposits ADD COLUMN IF NOT EXISTS pay_data TEXT",
    "ALTER TABLE providers ADD COLUMN IF NOT EXISTS logo TEXT",
    "ALTER TABLE providers ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0",
    "ALTER TABLE providers ADD COLUMN IF NOT EXISTS status TINYINT DEFAULT 1",
    "ALTER TABLE withdrawals ADD COLUMN IF NOT EXISTS admin_note TEXT",
    "ALTER TABLE withdrawals ADD COLUMN IF NOT EXISTS processed_at DATETIME",
];

foreach ($queries as $q) {
    try {
        $db->exec($q);
        $short = substr(trim($q), 0, 60);
        echo "✅ $short...\n";
    } catch (Exception $e) {
        $short = substr(trim($q), 0, 60);
        $msg = $e->getMessage();
        // Ignore "duplicate column" errors
        if (strpos($msg, 'Duplicate column') !== false || strpos($msg, 'already exists') !== false) {
            echo "⏭ $short (already exists)\n";
        } else {
            echo "❌ $short: $msg\n";
        }
    }
}

echo "\n✅ Setup complete!\n";
echo "</pre><a href='dashboard.php'>Go to Dashboard</a>";

// Add promo columns if missing
try{$db->exec("ALTER TABLE promos ADD COLUMN category VARCHAR(30) DEFAULT 'promosi' AFTER status");}catch(Exception $e){}
try{$db->exec("ALTER TABLE promos ADD COLUMN link VARCHAR(500) DEFAULT '#' AFTER image_url");}catch(Exception $e){}
try{$db->exec("ALTER TABLE promos ADD COLUMN button_text VARCHAR(50) DEFAULT 'Proses' AFTER link");}catch(Exception $e){}
echo "Promos columns OK\n";

// Referral reward claims
try{$db->exec("CREATE TABLE IF NOT EXISTS ref_claims (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    tier INT NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ref_claim (user_id, tier)
) ENGINE=InnoDB");}catch(Exception $e){}
echo "ref_claims OK\n";

// Auth token column
try{$db->exec("ALTER TABLE users ADD COLUMN auth_token VARCHAR(64) DEFAULT NULL AFTER password");}catch(Exception $e){}
try{$db->exec("ALTER TABLE users ADD INDEX idx_auth_token (auth_token)");}catch(Exception $e){}
echo "auth_token OK\n";

// Fund PIN column
try{$db->exec("ALTER TABLE users ADD COLUMN fund_pin VARCHAR(255) DEFAULT NULL AFTER password");}catch(Exception $e){}
echo "fund_pin OK\n";

// User banks
try{$db->exec("CREATE TABLE IF NOT EXISTS user_banks(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,bank_name VARCHAR(50),acc_name VARCHAR(100),acc_number VARCHAR(50),created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");}catch(Exception $e){}
echo "user_banks OK\n";

// Ensure admin user exists
$adminPw = password_hash('admin123', PASSWORD_BCRYPT);
try {
    $check = $db->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch();
    if (!$check) {
        $db->prepare("INSERT INTO users (username, password, email, phone, role, ref_code, balance, display_id) VALUES (?, ?, ?, ?, 'admin', 'ADMIN001', 99999000, 'admin')")
           ->execute(['admin', $adminPw, 'admin@local', '080000000000']);
        echo "Admin created: username=admin, password=admin123\n";
    } else {
        // Update password to known value
        $db->prepare("UPDATE users SET password=? WHERE role='admin' LIMIT 1")->execute([$adminPw]);
        echo "Admin password reset to: admin123\n";
    }
} catch(Exception $e) { echo "Admin error: ".$e->getMessage()."\n"; }

// Ensure role column exists
try{$db->exec("ALTER TABLE users ADD COLUMN role VARCHAR(10) DEFAULT 'user' AFTER balance");}catch(Exception $e){}
echo "role column OK\n";

// Withdrawals table
try{$db->exec("CREATE TABLE IF NOT EXISTS withdrawals(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,amount BIGINT UNSIGNED,bank_name VARCHAR(50),acc_name VARCHAR(100),acc_number VARCHAR(50),status VARCHAR(20) DEFAULT 'pending',admin_note TEXT,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,processed_at DATETIME DEFAULT NULL) ENGINE=InnoDB");}catch(Exception $e){}
try{$db->exec("ALTER TABLE withdrawals ADD COLUMN admin_note TEXT AFTER status");}catch(Exception $e){}
echo "withdrawals OK\n";

// User banks table
try{$db->exec("CREATE TABLE IF NOT EXISTS user_banks(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,bank_name VARCHAR(50),acc_name VARCHAR(100),acc_number VARCHAR(50),created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");}catch(Exception $e){}
echo "user_banks OK\n";

// ─── COLUMN MIGRATIONS (safe renames for existing DBs) ───
// withdrawals: rename bank→bank_name, acc_num→acc_number if old schema
$wdCols = array_column($db->query("SHOW COLUMNS FROM withdrawals")->fetchAll(),'Field');
if(in_array('bank',$wdCols)&&!in_array('bank_name',$wdCols)){
    try{$db->exec("ALTER TABLE withdrawals CHANGE `bank` `bank_name` VARCHAR(50)");echo "Migrated withdrawals.bank → bank_name\n";}catch(Exception $e){echo "Migrate warn: ".$e->getMessage()."\n";}
}
if(in_array('acc_num',$wdCols)&&!in_array('acc_number',$wdCols)){
    try{$db->exec("ALTER TABLE withdrawals CHANGE `acc_num` `acc_number` VARCHAR(50)");echo "Migrated withdrawals.acc_num → acc_number\n";}catch(Exception $e){}
}
// Add missing processed_at
try{$db->exec("ALTER TABLE withdrawals ADD COLUMN processed_at DATETIME DEFAULT NULL");}catch(Exception $e){}
// Add missing admin_note
try{$db->exec("ALTER TABLE withdrawals ADD COLUMN admin_note TEXT AFTER status");}catch(Exception $e){}
echo "Schema migration OK\n";

// deposits: add missing columns
try{$db->exec("ALTER TABLE deposits ADD COLUMN pay_url TEXT DEFAULT NULL");}catch(Exception $e){}
try{$db->exec("ALTER TABLE deposits ADD COLUMN pay_data TEXT DEFAULT NULL");}catch(Exception $e){}
try{$db->exec("ALTER TABLE deposits ADD COLUMN turnover_at_deposit BIGINT DEFAULT 0");}catch(Exception $e){}
try{$db->exec("ALTER TABLE deposits ADD COLUMN turnover_met TINYINT DEFAULT 0");}catch(Exception $e){}
echo "deposits columns OK\n";

// users: add missing columns
try{$db->exec("ALTER TABLE users ADD COLUMN rebate_claimed_to BIGINT UNSIGNED DEFAULT 0");}catch(Exception $e){}
try{$db->exec("ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL");}catch(Exception $e){}
try{$db->exec("ALTER TABLE users ADD COLUMN auth_token VARCHAR(64) DEFAULT NULL");}catch(Exception $e){}
try{$db->exec("ALTER TABLE users ADD INDEX idx_auth_token (auth_token)");}catch(Exception $e){}
try{$db->exec("ALTER TABLE users ADD COLUMN fund_pin VARCHAR(255) DEFAULT NULL");}catch(Exception $e){}
try{$db->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(10) DEFAULT 'm1'");}catch(Exception $e){}
try{$db->exec("ALTER TABLE users ADD COLUMN display_id VARCHAR(20) DEFAULT NULL");}catch(Exception $e){}
try{$db->exec("ALTER TABLE users ADD COLUMN display_name VARCHAR(50) DEFAULT NULL");}catch(Exception $e){}
echo "users columns OK\n";

// memos: add is_read column
try{$db->exec("ALTER TABLE memos ADD COLUMN is_read TINYINT DEFAULT 0");}catch(Exception $e){}
echo "memos columns OK\n";

// vip_claims table
try{$db->exec("CREATE TABLE IF NOT EXISTS vip_claims(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,claim_type VARCHAR(10),period VARCHAR(10),vip_level INT,amount BIGINT,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_claim(user_id,claim_type,period)) ENGINE=InnoDB");}catch(Exception $e){}
echo "vip_claims OK\n";

// rebates table
try{$db->exec("CREATE TABLE IF NOT EXISTS rebates(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,week_start DATE,week_end DATE,total_bet BIGINT DEFAULT 0,rebate_pct DECIMAL(5,2) DEFAULT 0,rebate_amount BIGINT DEFAULT 0,status VARCHAR(20) DEFAULT 'pending',paid_at DATETIME,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");}catch(Exception $e){}
echo "rebates OK\n";

// ref_claims table
try{$db->exec("CREATE TABLE IF NOT EXISTS ref_claims(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,tier INT NOT NULL,amount BIGINT UNSIGNED NOT NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_ref_claim(user_id,tier)) ENGINE=InnoDB");}catch(Exception $e){}
echo "ref_claims OK\n";

// promos table
try{$db->exec("CREATE TABLE IF NOT EXISTS promos(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(200),description TEXT,image_url VARCHAR(500),link VARCHAR(500) DEFAULT '#',button_text VARCHAR(50) DEFAULT 'Proses',category VARCHAR(30) DEFAULT 'promosi',status VARCHAR(20) DEFAULT 'active',created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");}catch(Exception $e){}
echo "promos OK\n";

// vip_claims - extend columns for new features
try{$db->exec("ALTER TABLE vip_claims MODIFY claim_type VARCHAR(30)");}catch(Exception $e){}
try{$db->exec("ALTER TABLE vip_claims MODIFY period VARCHAR(30)");}catch(Exception $e){}
try{$db->exec("ALTER TABLE vip_claims DROP INDEX uq_claim");}catch(Exception $e){}
try{$db->exec("ALTER TABLE vip_claims ADD UNIQUE KEY uq_claim2(user_id,claim_type,period,vip_level)");}catch(Exception $e){}
echo "vip_claims extended OK\n";

// Seed promos for all features
$seedPromos=[
  ['SPIN & MENANG JUTAAN!','Putar roda keberuntungan setiap hari, hadiah hingga 5 JUTA!','spin.php','promosi','Putar Sekarang'],
  ['NAIK LEVEL, GAJI NAIK!','Member VIP dapat gaji harian + mingguan + bulanan SELAMANYA','promo.php?tab=vip','promosi','Lihat Hadiah'],
  ['HADIAH SPESIAL MEMBER','Apresiasi setia member! Klaim bonus besar tiap tanggal 5','apresiasi.php','promosi','Ambil Hadiah'],
  ['BUKA PETI MISTERI','Bonus rahasia menanti! Semakin lama bermain, semakin besar','misteri.php','promosi','Buka Sekarang'],
  ['KALAH? KAMI GANTI!','Dana bantuan mingguan hingga 30% dari kerugian Anda','bantuan.php','promosi','Klaim Cashback'],
  ['GRATIS 100K EMAS!','Spin roulette gratis setiap hari, kumpulkan & tarik tunai!','roulette.php','promosi','Main Gratis'],
  ['LOGIN = CUAN!','Masuk 7 hari berturut-turut, bonus makin besar tiap hari!','checkin.php','promosi','Absen Sekarang'],
  ['DEPOSIT DAPAT EXTRA!','Semakin banyak deposit hari ini, semakin besar bonus tambahan!','bonusdepo.php','promosi','Deposit & Klaim'],
  ['TARUHAN = CASHBACK!','Setiap taruhan Anda menghasilkan rebate otomatis tanpa batas','promo.php?tab=rebate','promosi','Lihat Rebate'],
  ['AJAK TEMAN DAPAT 50K!','Bagikan link, teman daftar & deposit, langsung dapat bonus!','undang.php','promosi','Undang Sekarang'],
  ['TUKAR KODE BONUS','Tukar kode dan dapatkan bonus gratis setiap hari!','promo.php?tab=kode','promosi','Tukar Kode'],
];
try{
  $ins=$db->prepare("INSERT IGNORE INTO promos(title,description,image_url,link,category,button_text,status) VALUES(?,?,?,?,?,?,?)");
  foreach($seedPromos as $sp){
    // Check if this title already exists
    $chk=$db->prepare("SELECT id FROM promos WHERE title=? LIMIT 1");
    $chk->execute([$sp[0]]);
    if(!$chk->fetch()){
      $ins->execute([$sp[0],$sp[1],'',$sp[2],$sp[3],$sp[4],'active']);
    }
  }
  echo "Promo seeds OK\n";
}catch(Exception $e){echo "Promo seed error: ".$e->getMessage()."\n";}

// ═══ DEPOSIT RECONCILIATION SAFETY NET ═══
// Lock table untuk hard-guarantee anti double-credit (race callback+polling)
try{
    $db->exec("CREATE TABLE IF NOT EXISTS deposit_credits (
        tx_id VARCHAR(50) PRIMARY KEY,
        user_id INT NOT NULL,
        source VARCHAR(30) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_user (user_id)
    ) ENGINE=InnoDB");
    echo "✅ deposit_credits lock table OK\n";
}catch(Exception $e){echo "❌ deposit_credits error: ".$e->getMessage()."\n";}

// Index buat performance query pending (dipake reconcile)
try{$db->exec("ALTER TABLE deposits ADD INDEX idx_status_created (status, created_at)");echo "Index idx_status_created OK\n";}catch(Exception $e){echo "Index idx_status_created: skip (udah ada)\n";}
try{$db->exec("ALTER TABLE deposits ADD INDEX idx_status_expires (status, expires_at)");echo "Index idx_status_expires OK\n";}catch(Exception $e){echo "Index idx_status_expires: skip (udah ada)\n";}
try{$db->exec("ALTER TABLE deposits ADD INDEX idx_tx_id (tx_id)");echo "Index idx_tx_id OK\n";}catch(Exception $e){echo "Index idx_tx_id: skip (udah ada)\n";}
try{$db->exec("ALTER TABLE transactions ADD INDEX idx_ref_type (ref_id, type)");echo "Index idx_ref_type OK\n";}catch(Exception $e){echo "Index idx_ref_type: skip (udah ada)\n";}
echo "✅ Deposit reconciliation migration OK\n";

// ═══ AUTO-SEED logo + banner dari folder kalau belum ada ═══
try{
    // Logo: ambil file pertama di asset/uploads/logo/
    $logoExist=$db->query("SELECT value FROM settings WHERE `key`='logo_url'")->fetchColumn();
    if(empty($logoExist)){
        $logoDir=__DIR__.'/asset/uploads/logo/';
        if(is_dir($logoDir)){
            $logoFiles=glob($logoDir.'*.{png,jpg,jpeg,webp}',GLOB_BRACE)?:[];
            if(!empty($logoFiles)){
                $logoPath='asset/uploads/logo/'.basename($logoFiles[0]);
                $db->prepare("INSERT INTO settings(`key`,`value`) VALUES('logo_url',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([$logoPath]);
                echo "✅ Logo default diset: $logoPath\n";
            }
        }
    }

    // Banner: auto-insert 3 banner pertama
    $bCnt=$db->query("SELECT COUNT(*) FROM banners")->fetchColumn();
    if(intval($bCnt)==0){
        $bDir=__DIR__.'/asset/uploads/banner/';
        if(is_dir($bDir)){
            $bFiles=glob($bDir.'*.{png,jpg,jpeg,webp}',GLOB_BRACE)?:[];
            $ins=$db->prepare("INSERT INTO banners(image_url,sort_order,status) VALUES(?,?,1)");
            foreach(array_slice($bFiles,0,3) as $i=>$f){
                $ins->execute(['asset/uploads/banner/'.basename($f),$i]);
            }
            echo "✅ Banner default: ".count(array_slice($bFiles,0,3))." banner dimasukkan\n";
        }
    }
}catch(Exception $e){echo "Seed logo/banner: ".$e->getMessage()."\n";}

// ═══ AUTO-MATCH provider icons dari folder asset/uploads/provider/ ═══
// Dipake kalau provider udah di-sync dari NexusGGR tapi logo kosong
try{
    $dir=__DIR__.'/asset/uploads/provider/';
    if(is_dir($dir)){
        $files=glob($dir.'*.{png,jpg,jpeg,webp,svg,PNG,JPG}',GLOB_BRACE)?:[];
        $provs=$db->query("SELECT code,name,logo FROM providers")->fetchAll();
        $matched=0;
        foreach($provs as $p){
            if(!empty($p['logo']))continue;
            $needle=strtolower(preg_replace('/[^a-z0-9]/i','',$p['code']));
            $needleName=strtolower(preg_replace('/[^a-z0-9]/i','',$p['name']));
            foreach($files as $f){
                $base=strtolower(pathinfo($f,PATHINFO_FILENAME));
                $baseClean=preg_replace('/[^a-z0-9]/','',$base);
                if($baseClean&&(strpos($baseClean,$needle)!==false||strpos($baseClean,$needleName)!==false||($needle&&strpos($needle,$baseClean)!==false))){
                    $logoPath='asset/uploads/provider/'.basename($f);
                    $db->prepare("UPDATE providers SET logo=? WHERE code=?")->execute([$logoPath,$p['code']]);
                    $matched++;
                    break;
                }
            }
        }
        echo "✅ Provider icon auto-match: $matched provider mendapat logo\n";
    }
}catch(Exception $e){echo "Provider auto-match: ".$e->getMessage()."\n";}

echo "\n✅ SETUP COMPLETE\n";
echo "Admin: username=admin, password=admin123\n";
echo "⚠️  Delete or protect this file after setup!\n";

echo "</pre>";
