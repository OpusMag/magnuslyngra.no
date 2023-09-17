#.gitignore
import flask
from flask import Flask
#from flask import app
import os

HOST = 'localhost'
PORT = 8080

app = flask.Flask(__name__)

def make_site():
    
    app.config['SECRET_KEY'] = ' '
        

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
    app.run(HOST, PORT, debug=True)