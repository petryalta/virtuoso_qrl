# Virtuoso QRL to CSV
Tool for Openlink converting Virtuoso QRL log to CSV.
For more information run: php qrl.php --help

# Prepare QRL log

Put your qrl log file to `./data/virtuoso.qrl`

# Export QRL to CSV

```
docker-compose up
docker exec qrl_php php qrl.php --odbc --csv=./csv/1.csv --directly --qrl_log=virtuoso.qrl
```

check your file in ./csv/1.csv

# Replay QRL log

You can also replay qrl log on the development db.
Export the queries to dat file (doesn't work without exporting to csv):

```
docker-compose up
docker exec qrl_php php qrl.php --odbc --csv=./csv/1.csv --qf=./dat/1.dat --directly --qrl_log=virtuoso.qrl
```

Replay by time

```
docker exec qrl_php php qrl.php --play --odbc --qf=./dat/1.dat --time --mc=500 
```

# Running php script from host machine

Requirements: PHP 7.2, odbc driver
You can run this scripts directly from host machine (you need only configure db.conf)

# Contact

I can help you configure your logstash/kibana QRL analyzing dashboards.
For more information: t.me/awesomeshedy