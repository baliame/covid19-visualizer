.PHONY: all build clean

all: build/scraper build/app

build:
	mkdir -p build

clean:
	rm -rf build || true

build/scraper: Dockerfile | build
	docker build --target scraper -t covid19/scraper .
	touch build/scraper

build/app: Dockerfile | build
	docker build --target app -t covid19/app .
	touch build/app
