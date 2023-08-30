FROM php:8.1-fpm-alpine AS php
# Dockerビルドのベースイメージとして、php:8.1-fpm-alpineを使用することを指定しています。これはPHP 8.1 FPM（FastCGI Process Manager）イメージをAlpine Linuxベースの軽量なイメージとして使用することを示しています

RUN docker-php-ext-install pdo_mysql
# docker-php-ext-installコマンドを使用して、PHPイメージにPDO MySQL拡張をインストールしています。これにより、PHPアプリケーションがMySQLデータベースに接続するための機能が追加されます

RUN install -o www-data -g www-data -d /var/www/upload/image/
# installコマンドを使用して、/var/www/upload/image/ディレクトリを作成しています。-o www-data -g www-dataの部分は、ファイルとディレクトリの所有者とグループをwww-dataに設定しています。これは、Webサーバー（通常はNGINXやApacheなど）がファイルにアクセスできるようにするための設定です

RUN echo -e "post_max_size = 5M\nupload_max_filesize = 5M" >> ${PHP_INI_DIR}/php.ini

# Dockerfile 内で PHP の設定ファイルである php.ini に設定を追加するために使用されます。具体的には、php.ini ファイルに2つのパラメータ値を追加し、それらのパラメータは post_max_size と upload_max_filesize で、両者ともに 5M に設定されます
