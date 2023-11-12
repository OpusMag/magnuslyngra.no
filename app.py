#.gitignore
import flask
import ssl
import os
from flask_wtf.csrf import CSRFProtect
from flask import Flask, Response, escape, request, jsonify, make_response, redirect, url_for, session, render_template, flash



HOST = 'localhost'
PORT = 8080

app = flask.Flask(__name__)
csrf = CSRFProtect(app)

def make_site():
    
    app.config['SECRET_KEY'] = ' '
    
app.config.update(
    SESSION_COOKIE_SECURE=True,
    SESSION_COOKIE_HTTPONLY=True,
    SESSION_COOKIE_SAMESITE='Lax',
)

#We can set secure cookies in response
#Response.set_cookie('key', 'value', secure=True, httponly=True, samesite='Lax')

@app.after_request
def apply_caching(response):
    response.headers["X-Frame-Options"] = "SAMEORIGIN"
    response.headers["HTTP-HEADER"] = "VALUE"
    return response
        

@app.route('/', endpoint='index')
def index():
    return flask.render_template('index.html')

@app.route('/about', endpoint='about')
def about():
    return flask.render_template('about.html')

@app.route('/contact', endpoint='contact')
def about():
    return flask.render_template('contact.html')

@app.route('/projects', endpoint='projects')
def about():
    return flask.render_template('projects.html')

@app.route('/blog', endpoint='blog')
def about():
    return flask.render_template('blog.html')

@app.route('/api')
def api():
    return flask.jsonify({
        'name': 'flask',
        'version': flask.__version__,
        'env': os.environ.get('FLASK_ENV', 'development'),
        'debug': app.debug,
    })
    
if __name__ == '__main__':
    app.run(host=HOST, port=PORT, debug=True, ssl_context=('cert.pem', 'key.pem'))