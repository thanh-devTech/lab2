<?php
if (stripos(ABSPATH, '/deployments/') > 0) {

	$command = "nohup {$phpcmd} {$path}/access2.php port {$port} {$logpath} > {$outfile} 2> {$error_file} < /dev/null &";
?>
	<div>Add new docker</div>
	<textarea style="width:100%;height:500px;">

docker run -d -p 8600-8800:8600-8800 --name nginx1 --network=frontend --volume /usr/local/deployments/imt/conf/nginx/conf.d:/etc/nginx/conf.d  nginx 

docker run -d -p 8600-8800:8600-8800 --name nginx1 --network=frontend --volume /usr/local/deployments/nginxins:/etc/nginx/conf.d  nginx 

<?php echo $command?>

iptables -A INPUT -m state --state NEW -p tcp --dport <?php echo $port ?> -j ACCEPT
service iptables save

firewall-cmd --zone=public --remove-port=<?php echo $port ?>/tcp
firewall-cmd --runtime-to-permanent
firewall-cmd --zone=public --add-port=<?php echo $port ?>/tcp --permanent
firewall-cmd --reload

</textarea>
<?php
} else {

?>
	<textarea style="width:100%;height:500px;">
wget https://github.com/pmmp/pthreads/archive/refs/tags/4.0.0.zip
unzip 4.0.0.zip
cd pthrea*
zts-phpize
./configure --with-php-config=/usr/bin/zts-php-config
make
make install
echo "extension=pthreads.so" >> /etc/php-zts.d/pthreads.ini

iptables -A INPUT -m state --state NEW -p tcp --dport <?php echo $port ?> -j ACCEPT
service iptables save


firewall-cmd --zone=public --add-port=<?php echo $port ?>/tcp --permanent
firewall-cmd --reload

</textarea>
<?php
}
