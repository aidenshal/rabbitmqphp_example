#!/usr/bin/env python
import pika, time, mysql.connector

db = mysql.connector.connect(
    host = "localhost",
    user = "rabbit",
    passwd = "P99sn5q*h",
    database = "testDB"
)

cursorObject = db.cursor()
sql = "INSERT INTO users (name, screenname, email, password) VALUES (%s, %s, %s, %s)"

connection = pika.BlockingConnection(
    pika.ConnectionParameters(host='localhost'))
channel = connection.channel()

channel.queue_declare(queue='testQueue', durable=True, arguments={'x-queue-type': 'quorum'})
print(' [*] Waiting for messages. To exit press CTRL+C')

def callback(ch, method, properties, body):
    print(f" [x] Received {body.decode()}")
    time.sleep(body.count(b'.'))
    print(" [x] Done")
    ch.basic_ack(delivery_tag=method.delivery_tag)
    currentRequest = tuple(body.decode().split())
    '''currentRequest = body.decode()'''
    cursorObject.execute(sql, currentRequest)
    db.commit()

channel.basic_qos(prefetch_count=1)
channel.basic_consume(queue='testQueue', on_message_callback=callback)

channel.start_consuming()