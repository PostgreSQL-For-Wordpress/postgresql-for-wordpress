# WordPress with PostgreSQL

This image uses Wordpress 5.9.3 (used at the time it was created)
- PHP 7.4
- Apache
- WP4PG

## Decription

This image is for using Wordpress with the PostgreSQL database, based on the official image of [wordpress:php7.4-apache](https://github.com/docker-library/wordpress/blob/3b5c63b5673f298c14142c0c0e3e51edbdb17fd3/latest/php7.4/) and in the plugin [wp4pg](https://github.com/kevinoid/postgresql-for-wordpress) with this image it is possible to use wordpress with PostgreSQL running on apache2 with php7.4 .

## Using in docker-compose

Example used to build the image and test

````
version: "2.0"
services:
  db:
    image: postgres:14.1-alpine
    restart: always
    networks:
      - wordpress
    environment:
      - POSTGRES_USER=postgres
      - POSTGRES_PASSWORD=postgres
    volumes: 
      - db:/var/lib/postgresql/data
    ports:
      - "5432:5432"
  wordpress:
    restart: always
    networks:
      - wordpress
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: postgres
      WORDPRESS_DB_PASSWORD: postgres
      WORDPRESS_DB_NAME: postgres
    build:
      image: nerd4ever/wordpress-postgresql
      dockerfile: Dockerfile
    ports:
      - "8080:80"
    links:
      - db
    depends_on:
      - db
volumes:
  db:

networks:
  wordpress:
    driver: bridge
````

See an example of use in Kubernetes, to illustrate this example the database was installed via helm on a high availability cluster in a chart made by bitnami, the file **override-postgresql-values.yaml** must be your own with your customizations and the access credentials must be the same as those declared in the config-map, in addition, before starting wordpress it is necessary that the database is already created and that the user has access to it.

## Installing PostgreSQL with High Availability

For detailed information, visit the website of [bitnami](https://bitnami.com/stack/postgresql-ha/helm), this installation uses helm.

````
helm install postgresql bitnami/postgresql-ha --values override-postgresql-values.yaml:
````

## Using in kubernetes

Example extracted from the environment used to approve the image and test

````
apiVersion: v1
kind: ConfigMap
metadata:
  name: my-config-map
data:
  uploads.ini: |
    file_uploads = On
    upload_max_filesize = 256M
    post_max_size = 256M
    memory_limit = 64M
    max_execution_time = 600
  POSTGRES_DB: postgres
  POSTGRES_USER: postgres
  POSTGRES_PASSWORD: postgres
  POSTGRES_CLUSTER: postgresql-postgresql-ha-pgpool
  POSTGRES_REPLICATION_MANAGER_PASSWORD: postgres
---
apiVersion: v1
kind: PersistentVolume
metadata:
  name: website-data-storage
spec:
  storageClassName: website-data-storage
  capacity:
    storage: 5Gi
  accessModes:
    - ReadWriteOnce
  hostPath:
    path: "/mnt/kubernetes/website-data/"
    type: DirectoryOrCreate
---
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: website-disk
  labels:
    app: website
spec:
  storageClassName: website-data-storage
  accessModes:
    - ReadWriteOnce
  resources:
    requests:
      storage: 5Gi
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: website
  labels:
    app: website
spec:
  replicas: 1
  selector:
    matchLabels:
      app: website
      tier: frontend
  strategy:
    type: Recreate
  template:
    metadata:
      labels:
        app: website
        tier: frontend
    spec:
      volumes:
      - name: website-volume
        persistentVolumeClaim:
          claimName: website-disk
      - name: php-config-volume
        configMap:
          name: my-config-map
      containers:
      - image: nerd4ever/wordpress-postgresql:5.9.3
        name: website
        env:
        - name: WORDPRESS_DB_HOST
          valueFrom:
            configMapKeyRef:
              name: my-config-map
              key: POSTGRES_CLUSTER
        - name: WORDPRESS_DB_USER
          valueFrom:
            configMapKeyRef:
              name: my-config-map
              key: POSTGRES_USER
        - name: WORDPRESS_DB_PASSWORD
          valueFrom:
            configMapKeyRef:
              name: my-config-map
              key: POSTGRES_PASSWORD
        - name: WORDPRESS_DB_NAME
          value: "website"
        resources:
          limits:
            cpu: 500m
            memory: 2Gi
          requests:
            cpu: 250m
            memory: 512Mi
        ports:
        - containerPort: 80
          name: website
        volumeMounts:
        - name: website-volume
          mountPath: /var/www/html
        - name: php-config-volume
          mountPath: /usr/local/etc/php/conf.d/uploads.ini
          subPath: uploads.ini
---
apiVersion: v1
kind: Service
metadata:
  name: website
  labels:
    app: website
spec:
  ports:
    - port: 80
      targetPort: 80
  selector:
    app: website
    tier: frontend
````