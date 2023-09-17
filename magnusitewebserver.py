#.gitignore
from flask import Flask, Flask-login, Flask-sqlalchemy
import os

HOST = 'localhost'
PORT = 8080

def make_site():
    app = flask.Flask(__name__)
    app.config['SECRET_KEY'] = ' '
        

@app.route('/')
def index():
    return flask.render_template('index.html')

@app.route('/about')
def about():
    return flask.render_template('about.html')

@app.route('/contact')
def about():
    return flask.render_template('contact.html')

@app.route('/projects')
def about():
    return flask.render_template('projects.html')

@app.route('/blog')
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
    app.run(HOST, PORT, debug=True)