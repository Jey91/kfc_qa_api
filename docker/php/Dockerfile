FROM php:8.4-fpm-alpine

WORKDIR /var/www/html

RUN apk update && apk upgrade
RUN apk add --no-cache ${PHPIZE_DEPS} openssl-dev gnupg unixodbc-dev

# Install Microsoft ODBC drivers and SQL Server extensions
RUN curl -O https://download.microsoft.com/download/e/4/e/e4e67866-dffd-428c-aac7-8d28ddafb39b/msodbcsql17_17.10.2.1-1_amd64.apk
RUN curl -O https://download.microsoft.com/download/e/4/e/e4e67866-dffd-428c-aac7-8d28ddafb39b/mssql-tools_17.10.1.1-1_amd64.apk

# Install the Microsoft repository GPG keys
RUN curl -O https://download.microsoft.com/download/e/4/e/e4e67866-dffd-428c-aac7-8d28ddafb39b/msodbcsql17_17.10.2.1-1_amd64.sig
RUN curl -O https://download.microsoft.com/download/e/4/e/e4e67866-dffd-428c-aac7-8d28ddafb39b/mssql-tools_17.10.1.1-1_amd64.sig

# Install the packages
RUN apk add --allow-untrusted msodbcsql17_17.10.2.1-1_amd64.apk
RUN apk add --allow-untrusted mssql-tools_17.10.1.1-1_amd64.apk

# Add dependencies for SQL Server driver
RUN apk add --no-cache libstdc++

# Install and enable SQL Server extensions for PHP
RUN pecl install sqlsrv-5.10.1 pdo_sqlsrv-5.10.1
RUN docker-php-ext-enable sqlsrv pdo_sqlsrv

# Install other required extensions
RUN docker-php-ext-install pdo pdo_mysql
RUN docker-php-ext-configure calendar && docker-php-ext-install calendar

# Clean up
RUN apk del ${PHPIZE_DEPS}
RUN rm msodbcsql17_17.10.2.1-1_amd64.apk mssql-tools_17.10.1.1-1_amd64.apk msodbcsql17_17.10.2.1-1_amd64.sig mssql-tools_17.10.1.1-1_amd64.sig

# Verify PHP configuration
RUN php -m | grep -i sqlsrv

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN echo 'export PATH="$PATH:/usr/local/bin"' >> /root/.bashrc
ENV COMPOSER_ALLOW_SUPERUSER=1