import requests
import sqlite3

conn = sqlite3.connect('cases.sqlite')
c = conn.cursor()

c.execute('DELETE FROM data_points;')

places = requests.get("https://api.coronatab.app/places?typeId=country")
resp = places.json()

for elem in resp['data']:
	print('Scraping', elem['name'])
	data = requests.get("https://api.coronatab.app/places/{0}/data".format(elem['id'])).json()
	counter = 1
	for daily in data['data']:
		c.execute('INSERT INTO data_points (location, day, cases, deaths, date, recovered) VALUES ("{0}", {1}, {2}, {3}, "{4}", {5})'.format(elem['name'], counter, daily['cases'], daily['deaths'], daily['date'], daily['recovered']))
		counter = counter + 1

conn.commit()
conn.close()
