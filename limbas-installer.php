<?php
/**
 * @copyright Limbas GmbH <https://limbas.com>
 * @license https://opensource.org/licenses/MIT MIT
 *
 */

class Setup
{

    /**
     * Sets the language based on browser language
     * 
     * @return void
     */
    public static function loadLanguage(): void
    {
        $available = ['en', 'de'];

        if (!isset($_GET['lang'])) {
            $_GET['lang'] = 'en';
            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
                foreach ($languages as $lang) {
                    $lang = substr($lang, 0, 2);
                    if (in_array($lang, $available)) {
                        $_GET['lang'] = $lang;
                        break;
                    }
                }
            }
        }

        switch ($_GET['lang']) {
            case 'de':
                define('LANG', 'de');
                break;
            default:
                define('LANG', 'en');
        }
    }


    /**
     * Checks if all the dependencies are installed
     * @return bool|string true or error message
     */
    public static function checkDependencies(): bool|string
    {

        $missingDependencies = [];

        if (!class_exists('PharData')) {
            $missingDependencies[] = 'phar';
        }
        if (!function_exists('curl_init')) {
            $missingDependencies[] = 'curl';
        }

        if (!empty($missingDependencies)) {
            $error = '<p>' . self::lang('The following PHP modules are required to use the Limbas web installer:') . '</p><ul>';
            foreach ($missingDependencies as $missingDependency) {
                $error .= '<li>' . $missingDependency . '</li>';
            }
            return $error . '</ul>';
        }


        if (!is_writable('.')) {
            return self::lang('Can\'t write to the current directory. Please fix this by giving the webserver user write access to the directory.');
        }

        return true;
    }

    /**
     * @return bool
     */
    public static function checkIfWindows(): bool
    {
        return str_starts_with(PHP_OS, 'WIN');
    }

    /**
     * Downloads and extracts Limbas
     * @return bool|string true or error messages
     */
    public static function install(): bool|string
    {

        $directory = trim($_POST['directory']);
        $fileName = 'openlimbas.tar.gz';

        if (empty($directory)) {
            return self::lang('Please enter a directory.');
        }

        if ($directory !== '.') {
            $directory = './' . trim(trim($directory, '.'), '/');
        }

        define('DIR', $directory);

        // test if folder already exists
        if (!self::dirIsEmpty($directory)) {
            return self::lang('The selected directory is not empty. Please select an empty directory.');
        }

        // test if folder already exists
        if (is_dir($directory) && !is_writable($directory)) {
            return self::lang('Can\'t write to the target directory. Please fix this by giving the webserver user write access to the directory.');
        }

        // download the latest release
        if (!file_exists($fileName)) {
            $url = self::getLatestVersionUrl();
            if($url === false) {
                return self::lang('The URL of the latest version could not be retrieved from GitHub.');
            }
            $downloadStatus = self::downloadFile($url, $fileName);
            if ($downloadStatus !== true) {
                return self::lang('Source file could not be downloaded.');
            }
        }


        // unpacking
        try {
            $phar = new PharData($fileName);
            $phar->extractTo($directory);
        } catch (Throwable) {
            return self::lang('Extraction of the source file failed');
        }

        // deleting tar.gz file
        @unlink($fileName);

        return true;
    }


    /**
     * checks if given directory is empty
     *
     * @param string $directory
     * @return bool
     */
    private static function dirIsEmpty(string $directory): bool
    {
        $self = basename(__FILE__);
        $handle = opendir($directory);
        if ($handle) {
            while (false !== ($entry = readdir($handle))) {
                echo $entry . '<br>';
                if ($entry !== '.' && $entry !== '..' && $entry !== $self) {
                    closedir($handle);
                    return false;
                }
            }
            closedir($handle);
        }
        return true;
    }


    /**
     * Gets the download URL of the latest version from GitHub
     * 
     * @return bool|string
     */
    private static function getLatestVersionUrl(): bool|string {
        try {

            $options = [
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: PHP limbas/web-installer'
                    ]
                ]
            ];

            $context = stream_context_create($options);
            $githubData = json_decode(file_get_contents('https://api.github.com/repos/limbas/limbas/releases/latest', false,$context), true);
            $url = $githubData['assets'][0]['browser_download_url'];
        } catch (Throwable) {
            return false;
        }
        if(empty($url)) {
            return false;
        }
        return $url;
    }
    
    /**
     * Downloads a file and stores it in the local filesystem
     *
     * @param string $url
     * @param string $targetPath
     * @return bool
     */
    private static function downloadFile(string $url, string $targetPath): bool
    {

        $fp = fopen($targetPath, 'w+');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
        $data = curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        return $data === true;

    }

    /**
     * Get and check current installation step
     * @return int
     */
    public static function getStep(): int
    {
        $step = intval($_POST['step'] ?? 0);
        if (!in_array($step, [0, 1, 2])) {
            $step = 0;
        }
        return $step;
    }

    /**
     * Translates given string
     * 
     * @param string $value
     * @return string
     */
    public static function lang(string $value): string
    {

        $languages = [
            'en' => [],
            'de' => [
                'The Limbas Web Installer downloads the latest version of Limbas into the specified directory. The installation can then begin.' => 'Der Limbas Web Installer lädt die neuste Version von Limbas in das angegebene Verzeichnis. Anschließend kann mit der Installation begonnen werden.',
                'Enter a single "." to install in the current directory, or enter a subdirectory to install to:' => 'Geben Sie einen einzelnen "." ein, um im aktuellen Verzeichnis zu installieren, oder geben Sie ein Unterverzeichnis ein, in das installiert werden soll:',
                'The following PHP modules are required to use the Limbas web installer:' => 'Die folgenden PHP-Module sind erforderlich, um den Limbas Web Installer zu verwenden:',
                'Can\'t write to the current directory. Please fix this by giving the webserver user write access to the directory.' => 'Das aktuelle Verzeichnis ist nicht beschreibbar. Bitte beheben Sie dies, indem Sie dem Webserver-Benutzer Zugriff auf das Verzeichnis ermöglichen.',
                'Can\'t write to the target directory. Please fix this by giving the webserver user write access to the directory.' => 'Das Zielverzeichnis ist nicht beschreibbar. Bitte beheben Sie dies, indem Sie dem Webserver-Benutzer Zugriff auf das Verzeichnis ermöglichen.',
                'The selected directory is not empty. Please select an empty directory.' => 'Das ausgewählte Verzeichnis ist nicht leer. Bitte leeres Verzeichnis auswählen.',
                'Source file could not be downloaded.' => 'Quelldatei konnte nicht heruntergeladen werden.',
                'Extraction of the source file failed' => 'Das Entpacken des Archivs ist fehlgeschlagen',
                'Limbas is being downloaded...' => 'Limbas wird heruntergeladen..',
                'Please note that Limbas is not officially supported under Windows and may not work completely.' => 'Bitte beachten Sie, dass Limbas unter Windows nicht offiziell unterstützt wird und unter Umständen nicht vollständig funktioniert.',
                'Limbas was successfully downloaded.' => 'Limbas wurde erfolgreich heruntergeladen.',
                'Failed to remove installer script. Please remove it manually.' => 'Das Installationsskript konnte nicht gelöscht werden. Bitte manuell entfernen.',
                'Install now' => 'Jetzt installieren',
                'Back' => 'Zurück',
                'An error has occurred. Please try again.' => 'Ein Fehler ist aufgetreten. Bitte erneut versuchen.',
                'Continue to the installation' => 'Weiter zur Installation',
                'Make sure your domain points to the following directory:' => 'Stellen Sie sicher, dass Ihre Domain auf das nachfolgende Verzeichnis zeigt:',
                'Please enter a directory.' => 'Bitte Verzeichnis eintragen.',
                'The URL of the latest version could not be retrieved from GitHub.' => 'Die URL der neuesten Version konnte nicht von GitHub abgerufen werden.'
            ],


        ];

        if (!array_key_exists($value, $languages[LANG])) {
            return $value;
        }
        return $languages[LANG][$value];
    }

    /**
     * Deletes the current file
     * @return bool
     */
    public static function delete(): bool
    {
        @unlink(__FILE__);
        clearstatcache();
        return !file_exists(__FILE__);
    }


}

Setup::loadLanguage();

$step = Setup::getStep();

$installStatus = false;
if ($step === 1) {
    $installStatus = Setup::install();
}

?>
<!DOCTYPE html>
<html lang="<?= LANG ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Limbas Web Installer</title>
    <style>

        * {
            box-sizing: border-box;
        }

        html, body {
            background: #84827c;
            font-family: system-ui, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
        }

        #wrapper {
            display: flex;
            justify-content: center;
            padding-top: 3rem;
        }

        #content {
            flex: 0 0 auto;
            width: 100%;
            background: #fff;
            padding: 1.5rem;
            border-radius: 0.3rem;
            position: relative;
        }

        @media (min-width: 700px) {
            #content {
                width: 60%;
            }
        }

        @media (min-width: 1400px) {
            #content {
                width: 40%;
            }
        }

        #logo {
            text-align: center;
        }

        #logo img {
            height: 120px;
        }

        #lang-select {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
        }

        .btn {
            display: inline-block;
            font-weight: 400;
            line-height: 1.5;
            color: #2c2c2a;
            text-align: center;
            text-decoration: none;
            vertical-align: middle;
            cursor: pointer;
            user-select: none;
            background-color: transparent;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            border-radius: 0;
            font-size: inherit;
        }

        .btn-primary {
            color: #fff;
            background-color: #588100;
            border-color: #588100;
        }

        .text-center {
            text-align: center;
        }

        .alert {
            position: relative;
            padding: 1rem;
            color: inherit;
            background-color: transparent;
            border: 1px solid transparent;
            border-radius: 0.3rem;
        }

        .alert-success {
            color: #146c43;
            background-color: #d1e7dd;
            border-color: #a3cfbb;
        }

        .alert-warning {
            color: #997404;
            background-color: #ffe69c;
            border-color: #ffe69c;
        }

        .alert-danger {
            color: #b02a37;
            background-color: #f8d7da;
            border-color: #f1aeb5;
        }

        .mb-3 {
            margin-bottom: 1rem;
        }

        footer {
            padding-top: 4rem;
            color: #757575;
            font-size: 0.8rem;
            text-align: center;
        }

        footer a {
            color: #757575;
        }


        input {
            display: block;
            width: 100%;
            padding: .375rem .75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            background-clip: padding-box;
            border: 1px solid #dee2e6;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            border-radius: .375rem;
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }

        @keyframes spinner {
            to {
                transform: rotate(360deg);
            }
        }

        #spinner {
            width: 60px;
            height: 60px;
            border: 5px solid #588100;
            border-bottom-color: #f18e00;
            border-radius: 50%;
            display: inline-block;
            box-sizing: border-box;
            animation: spinner 1s linear infinite;
        }
    </style>
    <script>
        function changeLang() {
            let langSelect = document.getElementById('lang-select');
            let lang = langSelect.value;
            window.location.href = location.protocol + '//' + location.host + location.pathname + '?lang=' + lang;
        }

        function showLoading() {
            if (document.getElementById('directory').value !== '') {
                document.getElementById('install-form').style.display = 'none';
                document.getElementById('loading').style.display = 'block';
            }
        }
    </script>
</head>
<body>

<main id="wrapper">
    <div id="content">

        <select id="lang-select" onchange="changeLang()">
            <option value="en" <?= LANG === 'en' ? 'selected' : '' ?>>EN</option>
            <option value="de" <?= LANG === 'de' ? 'selected' : '' ?>>DE</option>
        </select>

        <div id="logo">
            <img src="data:image/png;base64,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"
                 alt="LIMBAS">
        </div>

        <h1 class="text-center">Limbas Web Installer</h1>

        <?php if ($step === 0): ?>

            <p><?= Setup::lang('The Limbas Web Installer downloads the latest version of Limbas into the specified directory. The installation can then begin.') ?></p>

            <?php $dependencies = Setup::checkDependencies(); ?>

            <?php if ($dependencies !== true): ?>

                <div class="alert alert-danger">
                    <p><?= $dependencies ?></p>
                </div>

            <?php else: ?>

                <div id="loading" class="text-center" style="display: none">
                    <div id="spinner"></div>
                    <br>
                    <?= Setup::lang('Limbas is being downloaded...'); ?>
                </div>

                <form action="?lang=<?= LANG ?>" method="post" id="install-form">
                    <?php if (Setup::checkIfWindows()): ?>
                        <div class="alert alert-warning mb-3">
                            <p><?= Setup::lang('Please note that Limbas is not officially supported under Windows and may not work completely.') ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <p>
                            <label for="directory"><?= Setup::lang('Enter a single "." to install in the current directory, or enter a subdirectory to install to:') ?></label>
                        </p>
                        <input type="text" name="directory" id="directory" value="./openlimbas" required="required"/>
                        <input type="hidden" name="step" value="1">
                    </div>

                    <div class="text-center">
                        <button class="btn btn-primary" type="submit"
                                onclick="showLoading()"><?= Setup::lang('Install now') ?></button>
                    </div>
                </form>

            <?php endif; ?>

        <?php elseif ($step === 1): ?>

            <?php if ($installStatus === true): ?>

                <div class="alert alert-success text-center mb-3">
                    <?= Setup::lang('Limbas was successfully downloaded.') ?>
                </div>

                <?php $deleteStatus = Setup::delete(); ?>

                <?php if ($deleteStatus !== true): ?>
                    <div class="alert alert-warning mb-3">
                        <?= Setup::lang('Failed to remove installer script. Please remove it manually.') ?>
                    </div>
                <?php endif; ?>

                <div class="alert alert-warning mb-3">
                    <?= Setup::lang('Make sure your domain points to the following directory:') ?>
                    <br><?= __DIR__ . DIRECTORY_SEPARATOR . trim('.', DIR) ?>public
                </div>

                <div class="text-center">
                    <a class="btn btn-primary"
                       href="<?= DIR ?>/public/install"><?= Setup::lang('Continue to the installation') ?></a>
                </div>

            <?php else: ?>

                <div class="alert alert-danger text-center mb-3">
                    <?= $installStatus ?>
                </div>
                <div class="text-center">
                    <a class="btn btn-primary" href=""><?= Setup::lang('Back') ?></a>
                </div>


            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-danger mb-3">
                <?= Setup::lang('An error has occurred. Please try again.') ?>
            </div>
            <div class="text-center">
                <a class="btn btn-primary" href=""><?= Setup::lang('Back') ?></a>
            </div>
        <?php endif; ?>


        <footer>Copyright &copy; <a href="https://limbas.com" target="_blank">Limbas GmbH</a></footer>
    </div>
</main>

</body>
</html>
