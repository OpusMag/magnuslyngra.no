from flask import Flask, Flask-login, Flask-sqlalchemy
import os

HOST = 'localhost'
PORT = 8080

def make_site():
    app = flask.Flask(__name__)
    app.config['SECRET_KEY'] = 'acALnaueifbvryhtkas'
        

@app.route('/')
def index():
    return flask.render_template('index.html')

@app.route('/about')

@app.route('/contact')

@app.route('/projects')

@app.route('/blog')

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