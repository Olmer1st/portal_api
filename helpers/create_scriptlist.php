<html>
  <head>
    <?php
      $current_dir = getcwd();
      function script_include($current_dir, $script_dir)
      {
        $dir = "{$current_dir}/{$script_dir}";
        if ($handle = opendir($dir)) {
            /* This is the correct way to loop over the directory. */
            while (false !== ($entry = readdir($handle))) {

                $extension = pathinfo($entry, PATHINFO_EXTENSION);
                $file = $script_dir . $entry;
                if($extension === "js"){
                  echo " <script type='text/javascript' src='{$file}'></script>" . PHP_EOL;
                }
                elseif ($extension === "css") {
                  echo " <link rel='stylesheet' type='text/css' href='{$file}'>" . PHP_EOL;
                }

            }
            closedir($handle);
        }
      }
      script_include($current_dir,"scripts/vendor/");
      script_include($current_dir, "scripts/");
      script_include($current_dir,"css/vendor/");
      script_include($current_dir,"css/");
    ?>
  </head>
    <body>
    </body>
</html>
