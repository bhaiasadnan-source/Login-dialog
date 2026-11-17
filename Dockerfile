FROM php:8.3-apache

# আপনার সব ফাইল কন্টেইনারের ভেতরে কপি করবে
COPY . /var/www/html/

# Apache-এর rewrite মডিউল চালু করবে (URL রাউটিং-এর জন্য দরকার হতে পারে)
RUN a2enmod rewrite

# পোর্ট ৮০ এক্সপোজ করবে
EXPOSE 80
