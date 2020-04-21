FROM python:latest AS scraper

VOLUME /app

RUN pip3 install requests

WORKDIR /app
CMD python3 scraper.py

FROM webdevops/php-nginx:7.4-alpine AS app

VOLUME /app
