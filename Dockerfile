# Production Dockerfile for Moodle 4.x LMS Deployment
# Designed for Railway, Render, Fly.io, and Local Containerized Stacks
FROM moodlehq/moodle-php-apache:8.3

# Metadata
LABEL maintainer="Uday Reddy <b.udayreddy5643@gmail.com>"
LABEL description="Production Moodle LMS with Cynaris Stats & AI Suite"
LABEL version="1.5.0"

# Set non-interactive debian frontend
ENV DEBIAN_FRONTEND=noninteractive

# Production environment variable defaults
ENV MOODLE_DOCKER_DBTYPE=mariadb \
    MOODLE_DOCKER_DBHOST=db \
    MOODLE_DOCKER_DBNAME=moodle \
    MOODLE_DOCKER_DBUSER=moodle \
    MOODLE_DOCKER_DBPASS=m@0dl3ing \
    MOODLE_DOCKER_DBCOLLATION=utf8mb4_unicode_ci \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

# Copy application source code into container web root
COPY . /var/www/html/

# Create moodledata directory outside web root and set strict Linux permissions
RUN mkdir -p /var/www/moodledata \
    && chown -R www-data:www-data /var/www/moodledata /var/www/html \
    && chmod -R 775 /var/www/moodledata

# Port configuration (Railway binds dynamic $PORT)
EXPOSE 80 8000

# Health check to ensure webserver availability
HEALTHCHECK --interval=30s --timeout=15s --start-period=45s --retries=3 \
    CMD curl -f -s -I http://localhost:80/ || exit 1

# Start Apache in foreground
CMD ["apache2-foreground"]
