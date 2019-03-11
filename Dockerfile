from ubuntu:latest

ENV TZ=Europe/Simferopol
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update && apt-get install -y php php-odbc unixodbc git

RUN git clone https://github.com/petryalta/virtuoso_qrl.git

ADD ./odbc.ini /etc/odbc.ini
ADD ./virtodbc.so /virtodbc.so
