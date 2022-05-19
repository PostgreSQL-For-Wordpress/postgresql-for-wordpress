# WordPress com PostgreSQL

Essa imagem usa o Wordpress 5.9.3 (usado na época que foi criada) 
- PHP 7.4
- Apache
- WP4PG

## Descrição

Essa imagem é permite usar Wordpress com o banco de dados PostgreSQL, baseado na imagem oficial do [wordpress:php7.4-apache](https://github.com/docker-library/wordpress/blob/3b5c63b5673f298c14142c0c0e3e51edbdb17fd3/latest/php7.4/) e no plugin [wp4pg](https://github.com/kevinoid/postgresql-for-wordpress) com essa imagem é possível usar o wordpress com o PostgreSQL rodando no apache2 com o php7.4 .

## Usando em  docker-composer 

Exemplo usado para buildar a imagem e testar

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

Veja um exemplo de uso no Kubernetes, para ilustrar esse exemplo o banco de dados foi instalado via helm em um cluster de alta disponibilidade em um chart feito pela bitnami, o arquivo **override-postgresql-values.yaml** deve ser seu próprio com suas customizações e as credênciais de acesso devem ser as mesmas que estão declaradas no config-map, além disso antes de iniciar o wordpress é necessário que o banco de dados já esteja criado e que o usuário tenha acesso a ele.

## Instalando o PostgreSQL com alta disponibilidade
Para informações detalhadas acesse o site da [bitnami](https://bitnami.com/stack/postgresql-ha/helm), essa instalação usa o helm.

````
helm install postgresql bitnami/postgresql-ha --values override-postgresql-values.yaml:
````

## Usando em kubernetes

Exemplo extraido do ambiente usado para homologar a imagem e testar

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