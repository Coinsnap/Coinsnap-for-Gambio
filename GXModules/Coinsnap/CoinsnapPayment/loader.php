<?php
// Converts namespace and class to the path to file
spl_autoload_register(
    function ($className){
        $libName = 'Coinsnap';
        $gxName = 'GXModules';
        
        if(strpos($className, $libName) !== 0 && strpos($className, $gxName) !== 0) {
            return;
        }

        else {
            $filePath =  (strpos($className, $gxName) !== 0)? 
                __DIR__ .'/CoinsnapAPILibrary'. str_replace([$libName, '\\'], ['', DIRECTORY_SEPARATOR], $className).'.php' : 
                __DIR__ .'/'. str_replace([$gxName, '\\'], ['', DIRECTORY_SEPARATOR], $className).'.php' ;
            
            if(file_exists($filePath)) {
                require_once($filePath);
                return;
            }
        }
    }
);
