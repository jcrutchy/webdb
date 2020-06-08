<?php

#phpinfo();

#echo file_get_contents("D:/Config Files/test.txt");

#pclose(popen('start "" ping localhost',"r"));
#pclose(popen('start "" d:\www\mrr\test.bat',"r"));
#die(shell_exec("tasklist"));
#die("test");

#die(shell_exec('dir "c:\\Program Files (x86)\\PHP\\"'));


#die(shell_exec('WMIC path win32_process get Caption,Processid,Commandline'));


#$command="wmic process where \"name='php.exe'\" get ProcessID,Commandline";
$command="wmic process where \"name='php.exe'\" get Commandline";
die(shell_exec($command));

# schtasks /change /disable /TN "task name" /S server_fqdn /U domain\user /P password
