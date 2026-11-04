#!/usr/bin/env python
import pika, sys

connection = pika.BlockingConnection(
    pika.ConnectionParameters(host='localhost'))
channel = connection.channel()

channel.queue_declare(queue='testQueue', durable=True, arguments={'x-queue-type': 'quorum'})

message = ' '.join(("testing", "newUser", "newTest@hotmail.com", "passedword")) or "Testing!"
'''sys.argv[1:]'''
channel.basic_publish(
    exchange='',
    routing_key='testQueue',
    body=message,
    properties=pika.BasicProperties(
        delivery_mode=pika.DeliveryMode.Persistent
    ))
print(f" [x] Sent {message}")
connection.close()